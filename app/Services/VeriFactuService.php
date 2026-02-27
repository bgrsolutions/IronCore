<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SalesDocument;
use App\Models\VerifactuEvent;
use JsonException;

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
                'description' => $this->normalizeUtf8String((string) ($line['description'] ?? '')),
                'qty' => $this->formatDecimal($line['qty'] ?? 0, 3),
                'unit_price' => $this->formatDecimal($line['unit_price'] ?? 0, 4),
                'tax_rate' => $this->formatDecimal($line['tax_rate'] ?? 0, 2),
                'line_gross' => $this->formatDecimal($line['line_gross'] ?? 0, 2),
            ];
        })->values()->all();

        return [
            'issuer_tax_id' => $this->normalizeUtf8String((string) ($company?->tax_id ?? '')),
            'company_id' => (int) $doc->company_id,
            'series' => $this->normalizeUtf8String((string) $doc->series),
            'number' => (int) $doc->number,
            'full_number' => $this->normalizeUtf8String($fullNumber),
            'doc_type' => $this->normalizeUtf8String((string) $doc->doc_type),
            'issue_date' => optional($doc->issue_date)->format('Y-m-d\TH:i:sP'),
            'currency' => (string) $doc->currency,
            'totals' => [
                'net' => $this->formatDecimal($doc->net_total, 2),
                'tax' => $this->formatDecimal($doc->tax_total, 2),
                'gross' => $this->formatDecimal($doc->gross_total, 2),
            ],
            'previous_hash' => $previousHash,
            'lines' => $lines,
        ];
    }

    /** @param array<string,mixed> $canonicalPayload */
    public function computeHash(array $canonicalPayload): string
    {
        try {
            $encoded = json_encode(
                $this->ksortRecursive($canonicalPayload),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $e) {
            $companyId = isset($canonicalPayload['company_id']) ? (int) $canonicalPayload['company_id'] : 0;
            if ($companyId > 0) {
                $this->recordEvent($companyId, null, 'verifactu.hash.encoding_error', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
            }

            throw $e;
        }

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
            'nif' => $this->normalizeUtf8String((string) ($company?->tax_id ?? '')),
            'num' => $this->normalizeUtf8String($fullNumber),
            'fecha' => optional($doc->issue_date)->format('Y-m-d'),
            'importe' => $this->formatDecimal($doc->gross_total, 2),
            'hash' => $hash,
        ], '', '&', PHP_QUERY_RFC3986);
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

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function ksortRecursive(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->ksortRecursive($value);
            }
        }

        return $data;
    }

    /**
     * @param  string|int|float|null  $value
     */
    private function formatDecimal($value, int $scale): string
    {
        if ($value === null) {
            return number_format(0, $scale, '.', '');
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));
            if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $normalized) === 1) {
                $sign = '';
                if (str_starts_with($normalized, '-') || str_starts_with($normalized, '+')) {
                    $sign = $normalized[0] === '-' ? '-' : '';
                    $normalized = substr($normalized, 1);
                }

                [$int, $frac] = array_pad(explode('.', $normalized, 2), 2, '');
                $fracLen = strlen($frac);

                if ($fracLen <= $scale) {
                    return $sign.$int.'.'.str_pad($frac, $scale, '0');
                }

                return number_format((float) ($sign.$int.'.'.$frac), $scale, '.', '');
            }
        }

        return number_format((float) $value, $scale, '.', '');
    }

    private function normalizeUtf8String(string $value): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if ($normalized !== false) {
                return $normalized;
            }
        }

        if (function_exists('mb_check_encoding') && ! mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $value;
    }
}
