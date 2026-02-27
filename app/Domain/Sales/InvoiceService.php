<?php

namespace App\Domain\Sales;

use App\Domain\Audit\AuditLogger;
use RuntimeException;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceSeriesService $invoiceSeriesService,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array<string, mixed> $company
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    public function createDraft(array $company, array $invoice, int $userId): array
    {
        $invoice['company_id'] = (int) $company['id'];
        $invoice['status'] = 'draft';
        $invoice['series_type'] = (string) ($invoice['series_type'] ?? 'T');
        $invoice['series_prefix'] = (string) ($invoice['series_prefix'] ?? $this->defaultPrefix($company, $invoice['series_type']));
        $invoice['number'] = null;
        $invoice['posted_at'] = null;
        $invoice['locked_at'] = null;

        $this->auditLogger->record(
            companyId: (int) $invoice['company_id'],
            action: 'invoice.created',
            auditableType: 'invoice',
            auditableId: (int) ($invoice['id'] ?? 0),
            userId: $userId,
            payload: ['series_type' => $invoice['series_type']]
        );

        return $invoice;
    }

    /**
     * @param array<string, mixed> $company
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    public function post(array $company, array $invoice, int $userId): array
    {
        if (($invoice['status'] ?? null) !== 'draft') {
            throw new RuntimeException('Only draft invoices can be posted.');
        }

        $seriesType = (string) $invoice['series_type'];
        $seriesPrefix = (string) $invoice['series_prefix'];

        $invoice['number'] = $this->invoiceSeriesService->nextNumber((int) $company['id'], $seriesType, $seriesPrefix);
        $invoice['status'] = 'posted';
        $invoice['posted_at'] = gmdate('c');
        $invoice['locked_at'] = $invoice['posted_at'];
        $invoice['payload_snapshot'] = json_encode($invoice, JSON_THROW_ON_ERROR);

        $this->auditLogger->record(
            companyId: (int) $invoice['company_id'],
            action: 'invoice.posted',
            auditableType: 'invoice',
            auditableId: (int) $invoice['id'],
            userId: $userId,
            payload: [
                'number' => $invoice['number'],
                'series_type' => $seriesType,
            ]
        );

        return $invoice;
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     */
    public function update(array $invoice, array $updates, int $userId): array
    {
        if (!empty($invoice['locked_at'])) {
            $this->auditLogger->record(
                companyId: (int) $invoice['company_id'],
                action: 'invoice.override_attempt',
                auditableType: 'invoice',
                auditableId: (int) $invoice['id'],
                userId: $userId,
                payload: ['updates' => $updates]
            );

            throw new RuntimeException('Posted invoices are immutable.');
        }

        return array_merge($invoice, $updates);
    }

    /**
     * @param array<string, mixed> $company
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    public function createCreditNote(array $company, array $invoice, string $reason, int $userId): array
    {
        if (($invoice['status'] ?? null) !== 'posted') {
            throw new RuntimeException('Credit notes can only be issued against posted invoices.');
        }

        $credit = [
            'id' => (int) ($invoice['id'] . '9'),
            'company_id' => (int) $company['id'],
            'series_type' => 'NC',
            'series_prefix' => (string) ($company['invoice_prefix_nc'] ?? 'NC'),
            'credit_note_id' => (int) $invoice['id'],
            'reason' => $reason,
            'status' => 'draft',
            'net_total' => -abs((float) ($invoice['net_total'] ?? 0)),
            'tax_total' => -abs((float) ($invoice['tax_total'] ?? 0)),
            'gross_total' => -abs((float) ($invoice['gross_total'] ?? 0)),
        ];

        $this->auditLogger->record(
            companyId: (int) $company['id'],
            action: 'invoice.credit_note_created',
            auditableType: 'invoice',
            auditableId: (int) $invoice['id'],
            userId: $userId,
            payload: ['reason' => $reason]
        );

        return $credit;
    }

    /**
     * @param array<string, mixed> $company
     */
    private function defaultPrefix(array $company, string $seriesType): string
    {
        return match ($seriesType) {
            'T' => (string) ($company['invoice_prefix_t'] ?? 'T'),
            'F' => (string) ($company['invoice_prefix_f'] ?? 'F'),
            'NC' => (string) ($company['invoice_prefix_nc'] ?? 'NC'),
            default => throw new RuntimeException('Unsupported series type.'),
        };
    }
}
