<?php

namespace App\Domain\Billing;

use App\Domain\Audit\AuditLogger;
use RuntimeException;

final class VendorBillService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $bill
     * @return array<string, mixed>
     */
    public function approve(array $bill, int $userId): array
    {
        if (($bill['status'] ?? 'draft') !== 'draft') {
            throw new RuntimeException('Only draft bills can be approved.');
        }

        $bill['status'] = 'approved';
        $bill['approved_by'] = $userId;
        $bill['approved_at'] = gmdate('c');

        $this->auditLogger->record(
            companyId: (int) $bill['company_id'],
            action: 'vendor_bill.approved',
            auditableType: 'vendor_bill',
            auditableId: (int) $bill['id'],
            userId: $userId,
            payload: ['status' => 'approved']
        );

        return $bill;
    }

    /**
     * @param array<string, mixed> $bill
     * @return array<string, mixed>
     */
    public function post(array $bill, int $userId): array
    {
        $status = $bill['status'] ?? 'draft';

        if ($status !== 'approved') {
            throw new RuntimeException('Only approved bills can be posted.');
        }

        if (!empty($bill['locked_at'])) {
            throw new RuntimeException('Bill is already locked.');
        }

        $totals = $this->calculateTotals($bill['lines'] ?? []);
        $bill['net_total'] = $totals['net_total'];
        $bill['tax_total'] = $totals['tax_total'];
        $bill['gross_total'] = $totals['gross_total'];
        $bill['status'] = 'posted';
        $bill['posted_at'] = gmdate('c');
        $bill['locked_at'] = $bill['posted_at'];
        $bill['posted_by'] = $userId;

        $this->auditLogger->record(
            companyId: (int) $bill['company_id'],
            action: 'vendor_bill.posted',
            auditableType: 'vendor_bill',
            auditableId: (int) $bill['id'],
            userId: $userId,
            payload: [
                'status' => 'posted',
                'posted_at' => $bill['posted_at'],
            ]
        );

        return $bill;
    }

    /**
     * @param array<string, mixed> $bill
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     */
    public function update(array $bill, array $updates, int $userId): array
    {
        if (!empty($bill['locked_at'])) {
            $this->auditLogger->record(
                companyId: (int) $bill['company_id'],
                action: 'vendor_bill.override_attempt',
                auditableType: 'vendor_bill',
                auditableId: (int) $bill['id'],
                userId: $userId,
                payload: ['updates' => $updates]
            );

            throw new RuntimeException('Posted bills are immutable.');
        }

        $merged = array_merge($bill, $updates);

        $this->auditLogger->record(
            companyId: (int) $bill['company_id'],
            action: 'vendor_bill.updated',
            auditableType: 'vendor_bill',
            auditableId: (int) $bill['id'],
            userId: $userId,
            payload: ['updates' => $updates]
        );

        return $merged;
    }

    /**
     * @param array<string, mixed> $bill
     * @return array<string, mixed>
     */
    public function cancel(array $bill, string $reason, int $userId): array
    {
        if (($bill['status'] ?? null) !== 'posted') {
            throw new RuntimeException('Only posted bills can be cancelled.');
        }

        $bill['status'] = 'cancelled';
        $bill['cancelled_at'] = gmdate('c');
        $bill['cancel_reason'] = $reason;

        $this->auditLogger->record((int) $bill['company_id'], 'vendor_bill.cancelled', 'vendor_bill', (int) $bill['id'], $userId, [
            'reason' => $reason,
        ]);

        return $bill;
    }

    /**
     * @param array<string, mixed> $bill
     */
    public function delete(array $bill, bool $isAdmin, int $userId): void
    {
        if (($bill['status'] ?? null) === 'posted' || !empty($bill['locked_at'])) {
            throw new RuntimeException('Posted records cannot be deleted.');
        }

        if (($bill['status'] ?? null) !== 'draft' || !$isAdmin) {
            throw new RuntimeException('Only admins can delete draft bills.');
        }

        $this->auditLogger->record((int) $bill['company_id'], 'vendor_bill.deleted', 'vendor_bill', (int) $bill['id'], $userId, [
            'status' => $bill['status'] ?? null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{net_total: float, tax_total: float, gross_total: float}
     */
    private function calculateTotals(array $lines): array
    {
        $net = 0.0;
        $tax = 0.0;
        $gross = 0.0;

        foreach ($lines as $line) {
            $net += (float) ($line['net_amount'] ?? 0);
            $tax += (float) ($line['tax_amount'] ?? 0);
            $gross += (float) ($line['gross_amount'] ?? 0);
        }

        return ['net_total' => round($net, 2), 'tax_total' => round($tax, 2), 'gross_total' => round($gross, 2)];
    }
}
