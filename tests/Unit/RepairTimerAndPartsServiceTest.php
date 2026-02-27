<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Repairs/RepairTimerService.php';
require_once __DIR__ . '/../../app/Domain/Repairs/RepairPartsService.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Repairs\RepairPartsService;
use App\Domain\Repairs\RepairTimerService;

function test_repair_timer_accepts_only_allowed_labour_slots(): void
{
    $service = new RepairTimerService();

    $repair = $service->addLabourEntry(['id' => 50, 'company_id' => 1], 15, 7);
    $repair = $service->addLabourEntry($repair, 30, 7);
    assert(count($repair['time_entries']) === 2);

    $failed = false;
    try {
        $service->addLabourEntry($repair, 45, 7);
    } catch (RuntimeException) {
        $failed = true;
    }
    assert($failed === true);
}

function test_repair_parts_consumption_creates_out_moves(): void
{
    $audit = new AuditLogger();
    $service = new RepairPartsService($audit);

    $moves = $service->consumeParts(
        ['id' => 51, 'company_id' => 1],
        [
            ['product_id' => 100, 'quantity' => 2, 'unit_cost' => 6.5],
            ['product_id' => 200, 'quantity' => 1, 'unit_cost' => 12],
        ],
        7
    );

    assert(count($moves) === 2);
    assert($moves[0]['direction'] === 'out');
    assert($moves[0]['reason'] === 'repair_parts_consumption');
    assert(count($audit->all()) === 1);
}
