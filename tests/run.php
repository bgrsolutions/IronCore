<?php

require_once __DIR__ . '/Unit/VendorBillServiceTest.php';
require_once __DIR__ . '/Unit/DocumentServiceTest.php';
require_once __DIR__ . '/Unit/VendorBillStockReceiverTest.php';
require_once __DIR__ . '/Unit/InvoiceServiceTest.php';
require_once __DIR__ . '/Unit/PrestaShopOrderPaidHandlerTest.php';
require_once __DIR__ . '/Unit/RepairWorkflowServiceTest.php';
require_once __DIR__ . '/Unit/RepairTimerAndPartsServiceTest.php';

$tests = [
    'test_vendor_bill_posting_locks_and_logs',
    'test_document_attachment_inherits_company_and_logs',
    'test_vendor_bill_posted_receipt_creates_stock_moves_and_updates_average_cost',
    'test_invoice_posting_assigns_series_number_and_locks',
    'test_credit_note_is_created_against_posted_invoice',
    'test_prestashop_paid_order_posts_ticket_or_invoice',
    'test_repair_intake_adds_diagnostic_fee_and_transitions',
    'test_repair_diagnostic_override_requires_manager_and_reason',
    'test_repair_timer_accepts_only_allowed_labour_slots',
    'test_repair_parts_consumption_creates_out_moves',
];

foreach ($tests as $test) {
    $test();
    echo "PASS: {$test}\n";
}
