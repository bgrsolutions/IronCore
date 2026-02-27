<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SalesDocument;
use App\Models\VerifactuEvent;

final class VeriFactuService
{
    /**
     * @param  array<string,mixed>  $immutablePayload
     * @return array<string,mixed>
     */
    public function canonicalizePayload(SalesDocument $doc, array $immutablePayload, ?string $previousHash, string $fullNumber): array
    {
        $company = Company::query()->find($doc->company_id);
        $lines = collect($immutablePayload['lines'] ?? [])->map(function (array $line): array {
            return [
                'line_no' => (int) ($line['line_no'] ?? 0),
                'description' => (string) ($line['description'] ?? ''),
                'qty' => number_format((float) ($line['qty'] ?? 0), 3, '.', ''),
                'unit_price' => number_format((float) ($line['unit_price'] ?? 0), 4, '.', ''),
                'tax_rate' => number_format((float) ($line['tax_rate'] ?? 0), 2, '.', ''),
                'line_gross' => number_format((float) ($line['line_gross'] ?? 0), 2, '.', ''),
            ];
        })->values()->all();

        return [
            'issuer_tax_id' => (string) ($company?->tax_id ?? ''),
            'company_id' => (int) $doc->company_id,
            'series' => (string) $doc->series,
            'number' => (int) $doc->number,
            'full_number' => $fullNumber,
            'doc_type' => (string) $doc->doc_type,
            'issue_date' => optional($doc->issue_date)->format('Y-m-d\TH:i:sP'),
            'currency' => (string) $doc->currency,
            'totals' => [
                'net' => number_format((float) $doc->net_total, 2, '.', ''),
                'tax' => number_format((float) $doc->tax_total, 2, '.', ''),
                'gross' => number_format((float) $doc->gross_total, 2, '.', ''),
            ],
            'previous_hash' => $previousHash,
            'lines' => $lines,
        ];
    }

    /** @param array<string,mixed> $canonicalPayload */
    public function computeHash(array $canonicalPayload): string
    {
        $encoded = json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', (string) $encoded);
    }

    public function resolvePreviousHash(SalesDocument $doc): ?string
    {
        return SalesDocument::query()
            ->where('company_id', $doc->company_id)
            ->where('series', $doc->series)
            ->where('status', 'posted')
            ->whereKeyNot($doc->id)
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('hash');
    }

    public function generateQrPayload(SalesDocument $doc, string $hash, string $fullNumber): string
    {
        $company = Company::query()->find($doc->company_id);

        return http_build_query([
            'nif' => (string) ($company?->tax_id ?? ''),
            'num' => $fullNumber,
            'fecha' => optional($doc->issue_date)->format('Y-m-d'),
            'importe' => number_format((float) $doc->gross_total, 2, '.', ''),
            'hash' => $hash,
        ]);
    }

    public function recordEvent(int $companyId, ?int $salesDocumentId, string $eventType, array $payload): void
    {
        VerifactuEvent::query()->create([
            'company_id' => $companyId,
            'sales_document_id' => $salesDocumentId,
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
