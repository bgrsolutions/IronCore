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
}
