<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\Company\CompanyContext;
use Illuminate\Http\Response;

class ReportExportController extends Controller
{
    public function __invoke(string $type, ReportService $service): Response
    {
        $companyId = (int) CompanyContext::get();
        abort_unless($companyId > 0, 400, 'Company context required');

        $rows = match ($type) {
            'sales-margin' => $service->salesMarginRows($companyId, now()->subDays(30)->startOfDay(), now()->endOfDay()),
            'dead-stock' => $service->deadStockRows($companyId),
            'negative-stock' => $service->negativeStockRows($companyId),
            'repair-profitability' => $service->repairProfitabilityRows($companyId),
            'reorder-suggestions' => $service->latestReorderSuggestionRows($companyId),
            default => abort(404),
        };

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$type.'-'.now()->format('YmdHis').'.csv"',
        ];

        $csv = $this->toCsv($rows);

        return response($csv, 200, $headers);
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
