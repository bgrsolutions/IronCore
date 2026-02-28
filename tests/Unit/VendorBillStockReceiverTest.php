<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Billing/AverageCostService.php';
require_once __DIR__ . '/../../app/Domain/Inventory/VendorBillStockReceiver.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Billing\AverageCostService;
use App\Domain\Inventory\VendorBillStockReceiver;

function test_vendor_bill_posted_receipt_creates_stock_moves_and_updates_average_cost(): void
{
    $audit = new AuditLogger();
    $receiver = new VendorBillStockReceiver(new AverageCostService(), $audit);

    $result = $receiver->receive(
        vendorBill: ['id' => 501, 'company_id' => 5, 'status' => 'posted'],
        billLines: [
            ['product_id' => 100, 'quantity' => 2, 'unit_price' => 10],
            ['product_id' => 100, 'quantity' => 2, 'unit_price' => 14],
            ['product_id' => 200, 'quantity' => 1, 'unit_price' => 30],
        ],
        productStates: [100 => ['on_hand' => 2.0, 'average_cost' => 8.0]],
        userId: 9
    );

    assert(count($result['stock_moves']) === 3);
    assert($result['product_states'][100]['on_hand'] === 6.0);
    assert($result['product_states'][100]['average_cost'] === 10.6667);
    assert($result['product_states'][200]['average_cost'] === 30.0);
    assert(count($audit->all()) === 1);
}
