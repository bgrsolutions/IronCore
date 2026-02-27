<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Repairs/RepairWorkflowService.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Repairs\RepairWorkflowService;

function test_repair_intake_adds_diagnostic_fee_and_transitions(): void
{
    $audit = new AuditLogger();
    $service = new RepairWorkflowService($audit);

    $repair = $service->intake(['id' => 10, 'company_id' => 1], 15);
    assert($repair['diagnostic_fee_added'] === true);
    assert($repair['diagnostic_fee_net'] === 45.00);

    $repair = $service->transition($repair, 'diagnosing', 15);
    $repair = $service->transition($repair, 'awaiting_approval', 15);
    assert($repair['status'] === 'awaiting_approval');
}

function test_repair_diagnostic_override_requires_manager_and_reason(): void
{
    $audit = new AuditLogger();
    $service = new RepairWorkflowService($audit);

    $repair = ['id' => 11, 'company_id' => 1, 'diagnostic_fee_net' => 45.00];

    $failed = false;
    try {
        $service->overrideDiagnosticFee($repair, 40.00, 20, '', true);
    } catch (RuntimeException) {
        $failed = true;
    }
    assert($failed === true);

    $updated = $service->overrideDiagnosticFee($repair, 40.00, 20, 'Loyalty discount', true);
    assert($updated['diagnostic_fee_net'] === 40.00);
}
