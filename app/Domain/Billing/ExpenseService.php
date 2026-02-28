<?php

namespace App\Domain\Billing;

use App\Domain\Audit\AuditLogger;
use RuntimeException;

final class ExpenseService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /** @param array<string,mixed> $expense */
    public function approve(array $expense, int $userId): array
    {
        if (($expense['status'] ?? 'draft') !== 'draft') {
            throw new RuntimeException('Only draft expenses can be approved.');
        }

        $expense['status'] = 'approved';
        $expense['approved_at'] = gmdate('c');

        $this->auditLogger->record((int) $expense['company_id'], 'expense.approved', 'expense', (int) $expense['id'], $userId);

        return $expense;
    }

    /** @param array<string,mixed> $expense */
    public function post(array $expense, int $userId): array
    {
        if (($expense['status'] ?? null) !== 'approved') {
            throw new RuntimeException('Only approved expenses can be posted.');
        }

        $totals = $this->calculateTotals($expense['lines'] ?? []);
        $expense['net_total'] = $totals['net_total'];
        $expense['tax_total'] = $totals['tax_total'];
        $expense['gross_total'] = $totals['gross_total'];
        $expense['status'] = 'posted';
        $expense['posted_at'] = gmdate('c');
        $expense['locked_at'] = $expense['posted_at'];

        $this->auditLogger->record((int) $expense['company_id'], 'expense.posted', 'expense', (int) $expense['id'], $userId, $totals);

        return $expense;
    }

    /** @param array<string,mixed> $expense @param array<string,mixed> $updates */
    public function update(array $expense, array $updates, int $userId): array
    {
        if (!empty($expense['locked_at'])) {
            $this->auditLogger->record((int) $expense['company_id'], 'expense.override_attempt', 'expense', (int) $expense['id'], $userId, ['updates' => $updates]);
            throw new RuntimeException('Posted expenses are immutable.');
        }

        $merged = array_merge($expense, $updates);
        $this->auditLogger->record((int) $expense['company_id'], 'expense.updated', 'expense', (int) $expense['id'], $userId, ['updates' => $updates]);

        return $merged;
    }

    /** @param array<string,mixed> $expense */
    public function cancel(array $expense, string $reason, int $userId): array
    {
        if (($expense['status'] ?? null) !== 'posted') {
            throw new RuntimeException('Only posted expenses can be cancelled.');
        }

        $expense['status'] = 'cancelled';
        $expense['cancelled_at'] = gmdate('c');
        $expense['cancel_reason'] = $reason;

        $this->auditLogger->record((int) $expense['company_id'], 'expense.cancelled', 'expense', (int) $expense['id'], $userId, ['reason' => $reason]);

        return $expense;
    }

    /** @param array<string,mixed> $expense */
    public function delete(array $expense, bool $isAdmin, int $userId): void
    {
        if (($expense['status'] ?? null) === 'posted' || !empty($expense['locked_at'])) {
            throw new RuntimeException('Posted records cannot be deleted.');
        }

        if (($expense['status'] ?? null) !== 'draft' || !$isAdmin) {
            throw new RuntimeException('Only admins can delete draft expenses.');
        }

        $this->auditLogger->record((int) $expense['company_id'], 'expense.deleted', 'expense', (int) $expense['id'], $userId, ['status' => $expense['status'] ?? null]);
    }

    /** @param array<int, array<string,mixed>> $lines */
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
