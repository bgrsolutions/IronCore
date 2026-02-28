<?php

namespace App\Domain\Repairs;

use App\Domain\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\Repair;
use App\Models\RepairStatusHistory;
use App\Models\User;
use App\Services\RepairMetricsService;
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

    public function __construct(private readonly AuditLogger $auditLogger, private readonly RepairMetricsService $repairMetricsService)
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

        if (! in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
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

    public function transitionModel(Repair $repair, string $toStatus, int $userId, ?string $reason = null): Repair
    {
        $fromStatus = (string) $repair->status;

        if (! in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
            throw new RuntimeException(sprintf('Invalid transition from %s to %s.', $fromStatus, $toStatus));
        }

        if ($toStatus === 'collected' && ! $repair->signatures()->where('signature_type', 'pickup')->exists()) {
            throw new RuntimeException('Cannot collect repair without pickup signature.');
        }

        if ($toStatus === 'invoiced') {
            $salesDocument = $repair->linkedSalesDocument;
            if (! $salesDocument || $salesDocument->status !== 'posted') {
                throw new RuntimeException('Cannot mark invoiced without linked posted sales document.');
            }
        }

        $this->guardTimeLeakTransition($repair, $toStatus, $userId, $reason);

        $repair->update(['status' => $toStatus]);

        RepairStatusHistory::query()->create([
            'company_id' => $repair->company_id,
            'repair_id' => $repair->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $userId,
            'reason' => $reason,
            'changed_at' => now(),
        ]);

        $this->auditLogger->record(
            companyId: (int) $repair->company_id,
            action: 'repair.status_changed',
            auditableType: 'repair',
            auditableId: (int) $repair->id,
            userId: $userId,
            payload: [
                'from' => $fromStatus,
                'to' => $toStatus,
                'reason' => $reason,
            ]
        );

        return $repair->refresh();
    }

    public function isTimeLeakBlocked(Repair $repair): bool
    {
        $requireLabour = (bool) config('repairs.require_labour_if_time_logged', true);

        return $requireLabour && $this->repairMetricsService->hasTimeLeak($repair);
    }

    private function guardTimeLeakTransition(Repair $repair, string $toStatus, int $userId, ?string $reason): void
    {
        if (! in_array($toStatus, ['ready', 'collected'], true) || ! $this->isTimeLeakBlocked($repair)) {
            return;
        }

        $loggedMinutes = $this->repairMetricsService->loggedMinutes($repair);
        $threshold = (int) config('repairs.time_leak_threshold_minutes', 15);

        $user = User::query()->find($userId);
        if (! $user || ! $user->hasAnyRole(['manager', 'admin'])) {
            throw new RuntimeException('Transition blocked: time logged but no labour billed. Manager/admin override required.');
        }

        if ((bool) config('repairs.manager_override_requires_reason', true) && trim((string) $reason) === '') {
            throw new RuntimeException('Transition override reason is required.');
        }

        AuditLog::query()->create([
            'company_id' => $repair->company_id,
            'user_id' => $userId,
            'action' => 'repair_time_leak_override',
            'auditable_type' => 'repair',
            'auditable_id' => $repair->id,
            'payload' => [
                'event_type' => 'repair_time_leak_override',
                'reason' => $reason,
                'repair_id' => $repair->id,
                'logged_minutes' => $loggedMinutes,
                'threshold' => $threshold,
                'to_status' => $toStatus,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $repair
     * @return array<string, mixed>
     */
    public function overrideDiagnosticFee(array $repair, float $newNetFee, int $userId, string $reason, bool $isManager): array
    {
        if (! $isManager) {
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
