<?php

namespace App\Services;

use App\Models\AccountantExportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class AccountantExportService
{
    public function generateBatch(int $companyId, string $fromDate, string $toDate, bool $breakdownByStore = false, ?int $userId = null): AccountantExportBatch
    {
        $batch = AccountantExportBatch::query()->create([
            'company_id' => $companyId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'breakdown_by_store' => $breakdownByStore,
            'summary_payload' => [],
            'zip_path' => '',
            'zip_hash' => '',
            'generated_by_user_id' => $userId,
            'generated_at' => now(),
        ]);

        $baseDir = 'accountant_exports/batch_'.$batch->id;
        Storage::disk('local')->makeDirectory($baseDir);

        $salesDocs = $this->salesDocsRows($companyId, $fromDate, $toDate);
        $salesLines = $this->salesLinesRows($companyId, $fromDate, $toDate);
        $salesIgic = $this->salesIgicSummary($companyId, $fromDate, $toDate, $breakdownByStore);

        $vendorBills = $this->vendorBillsRows($companyId, $fromDate, $toDate);
        $vendorBillLines = $this->vendorBillLinesRows($companyId, $fromDate, $toDate);
        $purchaseIgic = $this->purchaseIgicSummary($companyId, $fromDate, $toDate, $breakdownByStore);

        $outputTax = round((float) collect($salesIgic)->sum('igic_tax_total'), 2);
        $inputTax = round((float) collect($purchaseIgic)->sum('igic_tax_total'), 2);

        $igicSummary = [[
            'output_tax_total' => $outputTax,
            'input_tax_total' => $inputTax,
            'net_payable_estimate' => round($outputTax - $inputTax, 2),
        ]];

        $files = [
            'sales_docs.csv' => $salesDocs,
            'sales_lines.csv' => $salesLines,
            'sales_igic_summary.csv' => $salesIgic,
            'vendor_bills.csv' => $vendorBills,
            'vendor_bill_lines.csv' => $vendorBillLines,
            'purchase_igic_summary.csv' => $purchaseIgic,
            'igic_summary.csv' => $igicSummary,
        ];

        foreach ($files as $name => $rows) {
            $path = $baseDir.'/'.$name;
            $csv = $this->toCsv($rows);
            Storage::disk('local')->put($path, $csv);
            $batch->files()->create([
                'file_name' => $name,
                'file_path' => $path,
                'rows_count' => count($rows),
                'sha256' => hash('sha256', $csv),
                'created_at' => now(),
            ]);
        }

        $zipPath = $baseDir.'/accountant-export-'.$batch->id.'.zip';
        $fullZipPath = Storage::disk('local')->path($zipPath);
        $zip = new ZipArchive();
        $zip->open($fullZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (array_keys($files) as $name) {
            $zip->addFile(Storage::disk('local')->path($baseDir.'/'.$name), $name);
        }
        $zip->close();

        $zipHash = hash_file('sha256', $fullZipPath) ?: '';

        $batch->update([
            'summary_payload' => [
                'sales_docs_count' => count($salesDocs),
                'vendor_bills_count' => count($vendorBills),
                'output_tax_total' => $outputTax,
                'input_tax_total' => $inputTax,
                'net_payable_estimate' => round($outputTax - $inputTax, 2),
                'breakdown_by_store' => $breakdownByStore,
            ],
            'zip_path' => $zipPath,
            'zip_hash' => $zipHash,
        ]);

        return $batch->fresh(['files']);
    }

    /** @return array<int,array<string,mixed>> */
    private function salesDocsRows(int $companyId, string $fromDate, string $toDate): array
    {
        return DB::table('sales_documents')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween(DB::raw('date(issue_date)'), [$fromDate, $toDate])
            ->select(['id', 'store_location_id', 'doc_type', 'full_number', 'issue_date', 'net_total', 'tax_total', 'gross_total'])
            ->get()->map(fn ($r): array => (array) $r)->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function salesLinesRows(int $companyId, string $fromDate, string $toDate): array
    {
        return DB::table('sales_document_lines')
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->where('sales_documents.company_id', $companyId)
            ->where('sales_documents.status', 'posted')
            ->whereBetween(DB::raw('date(sales_documents.issue_date)'), [$fromDate, $toDate])
            ->select([
                'sales_document_lines.sales_document_id',
                'sales_documents.store_location_id',
                'sales_documents.doc_type',
                'sales_document_lines.product_id',
                'sales_document_lines.description',
                'sales_document_lines.qty',
                'sales_document_lines.tax_rate',
                'sales_document_lines.line_net',
                'sales_document_lines.line_tax',
                'sales_document_lines.line_gross',
            ])
            ->get()
            ->map(function ($r): array {
                $row = (array) $r;
                if (($row['doc_type'] ?? null) === 'credit_note' && (float) $row['line_net'] > 0) {
                    $row['line_net'] = -1 * (float) $row['line_net'];
                    $row['line_tax'] = -1 * (float) $row['line_tax'];
                    $row['line_gross'] = -1 * (float) $row['line_gross'];
                }

                return $row;
            })
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function salesIgicSummary(int $companyId, string $fromDate, string $toDate, bool $breakdownByStore): array
    {
        $rows = collect($this->salesLinesRows($companyId, $fromDate, $toDate));
        $grouped = $rows->groupBy(function (array $row) use ($breakdownByStore): string {
            $store = $breakdownByStore ? (string) ($row['store_location_id'] ?? 'null') : 'all';

            return $store.'|'.number_format((float) ($row['tax_rate'] ?? 0), 2, '.', '');
        });

        return $grouped->map(function ($group, string $key) use ($breakdownByStore): array {
            [$store, $rate] = explode('|', $key);

            return array_filter([
                'store_location_id' => $breakdownByStore ? ($store === 'null' ? null : (int) $store) : null,
                'tax_rate' => (float) $rate,
                'taxable_base_net' => round((float) $group->sum('line_net'), 2),
                'igic_tax_total' => round((float) $group->sum('line_tax'), 2),
                'gross_total' => round((float) $group->sum('line_gross'), 2),
            ], fn ($v) => $v !== null || $breakdownByStore);
        })->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function vendorBillsRows(int $companyId, string $fromDate, string $toDate): array
    {
        return DB::table('vendor_bills')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->select(['id', 'store_location_id', 'supplier_id', 'invoice_number', 'invoice_date', 'net_total', 'tax_total', 'gross_total'])
            ->get()->map(fn ($r): array => (array) $r)->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function vendorBillLinesRows(int $companyId, string $fromDate, string $toDate): array
    {
        return DB::table('vendor_bill_lines')
            ->join('vendor_bills', 'vendor_bills.id', '=', 'vendor_bill_lines.vendor_bill_id')
            ->where('vendor_bills.company_id', $companyId)
            ->where('vendor_bills.status', 'posted')
            ->whereBetween('vendor_bills.invoice_date', [$fromDate, $toDate])
            ->select([
                'vendor_bill_lines.vendor_bill_id',
                'vendor_bills.store_location_id',
                'vendor_bill_lines.product_id',
                'vendor_bill_lines.description',
                'vendor_bill_lines.quantity',
                'vendor_bill_lines.net_amount',
                'vendor_bill_lines.tax_rate',
                'vendor_bill_lines.tax_amount',
                'vendor_bill_lines.gross_amount',
            ])
            ->get()->map(fn ($r): array => (array) $r)->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function purchaseIgicSummary(int $companyId, string $fromDate, string $toDate, bool $breakdownByStore): array
    {
        $rows = collect($this->vendorBillLinesRows($companyId, $fromDate, $toDate));
        $grouped = $rows->groupBy(function (array $row) use ($breakdownByStore): string {
            $store = $breakdownByStore ? (string) ($row['store_location_id'] ?? 'null') : 'all';

            return $store.'|'.number_format((float) ($row['tax_rate'] ?? 0), 2, '.', '');
        });

        return $grouped->map(function ($group, string $key) use ($breakdownByStore): array {
            [$store, $rate] = explode('|', $key);

            return array_filter([
                'store_location_id' => $breakdownByStore ? ($store === 'null' ? null : (int) $store) : null,
                'tax_rate' => (float) $rate,
                'taxable_base_net' => round((float) $group->sum('net_amount'), 2),
                'igic_tax_total' => round((float) $group->sum('tax_amount'), 2),
                'gross_total' => round((float) $group->sum('gross_amount'), 2),
            ], fn ($v) => $v !== null || $breakdownByStore);
        })->values()->all();
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function toCsv(array $rows): string
    {
        if ($rows === []) {
            return "\n";
        }

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($fp, array_values($row));
        }
        rewind($fp);
        $csv = stream_get_contents($fp) ?: '';
        fclose($fp);

        return $csv;
    }
}
