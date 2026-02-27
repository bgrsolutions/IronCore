<?php

namespace App\Domain\Repairs;

use App\Domain\Audit\AuditLogger;
use RuntimeException;

final class RepairWorkflowService
{
    /** @var array<string, array<int, string>> */
    private const TRANSITIONS = [
        'intake' => ['diagnosing'],
        'diagnosing' => ['awaiting_approval'],
        'awaiting_approval' => ['in_progress', 'collected'],
        'in_progress' => ['waiting_parts', 'ready'],
        'waiting_parts' => ['in_progress', 'ready'],
        'ready' => ['collected', 'invoiced'],
        'collected' => ['invoiced'],
        'invoiced' => [],
    ];

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $repair
     * @return array<string, mixed>
     */
    public function intake(array $repair, int $userId): array
    {
        $repair['status'] = 'intake';
        $repair['diagnostic_fee_added'] = true;
        $repair['diagnostic_fee_product_name'] = 'Diagnostic Fee';
        $repair['diagnostic_fee_net'] = 45.00;

        $this->auditLogger->record(
            companyId: (int) $repair['company_id'],
            action: 'repair.intake_created',
            auditableType: 'repair',
            auditableId: (int) $repair['id'],
            userId: $userId,
            payload: [
                'diagnostic_fee_added' => true,
                'diagnostic_fee_net' => 45.00,
            ]
        );

        return $repair;
    }

    /**
     * @param array<string, mixed> $repair
     * @return array<string, mixed>
     */
    public function transition(array $repair, string $toStatus, int $userId, ?string $reason = null): array
    {
        $fromStatus = (string) ($repair['status'] ?? 'intake');

        if (!in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
            throw new RuntimeException(sprintf('Invalid transition from %s to %s.', $fromStatus, $toStatus));
        }

        $repair['status'] = $toStatus;

        $this->auditLogger->record(
            companyId: (int) $repair['company_id'],
            action: 'repair.status_changed',
            auditableType: 'repair',
            auditableId: (int) $repair['id'],
            userId: $userId,
            payload: [
                'from' => $fromStatus,
                'to' => $toStatus,
                'reason' => $reason,
            ]
        );

        return $repair;
    }

    /**
     * @param array<string, mixed> $repair
     * @return array<string, mixed>
     */
    public function overrideDiagnosticFee(array $repair, float $newNetFee, int $userId, string $reason, bool $isManager): array
    {
        if (!$isManager) {
            throw new RuntimeException('Only manager can override diagnostic fee.');
        }

        if ($reason === '') {
            throw new RuntimeException('Override reason is required.');
        }

        $repair['diagnostic_fee_net'] = round($newNetFee, 2);

        $this->auditLogger->record(
            companyId: (int) $repair['company_id'],
            action: 'repair.diagnostic_fee_overridden',
            auditableType: 'repair',
            auditableId: (int) $repair['id'],
            userId: $userId,
            payload: [
                'new_net_fee' => $repair['diagnostic_fee_net'],
                'reason' => $reason,
            ]
        );

        return $repair;
    }
}
