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
        'gross_total' => 200.00,
    ];

    $approved = $service->approve($bill, userId: 99);
    assert($approved['status'] === 'approved');

    $posted = $service->post($approved, userId: 99);
    assert($posted['status'] === 'posted');
    assert(!empty($posted['locked_at']));

    $failed = false;
    try {
        $service->update($posted, ['gross_total' => 250.00], userId: 99);
    } catch (RuntimeException) {
        $failed = true;
    }

    assert($failed === true);
    assert(count($audit->all()) === 3);
}
