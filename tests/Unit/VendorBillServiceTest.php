<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Billing/VendorBillService.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Billing\VendorBillService;

function test_vendor_bill_posting_locks_and_logs(): void
{
    $audit = new AuditLogger();
    $service = new VendorBillService($audit);

    $bill = [
        'id' => 10,
        'company_id' => 1,
        'status' => 'draft',
        'lines' => [
            ['net_amount' => 100, 'tax_amount' => 7, 'gross_amount' => 107],
            ['net_amount' => 50, 'tax_amount' => 3.5, 'gross_amount' => 53.5],
        ],
    ];

    $approved = $service->approve($bill, userId: 99);
    $posted = $service->post($approved, userId: 99);
    assert($posted['gross_total'] === 160.5);
    assert(!empty($posted['locked_at']));

    $cancelled = $service->cancel($posted, 'Wrong supplier', userId: 99);
    assert($cancelled['status'] === 'cancelled');

    $failed = false;
    try {
        $service->delete($posted, isAdmin: true, userId: 99);
    } catch (RuntimeException) {
        $failed = true;
    }

    assert($failed === true);
    assert(count($audit->all()) === 4);
}
