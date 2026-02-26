<?php

require_once __DIR__ . '/Unit/VendorBillServiceTest.php';
require_once __DIR__ . '/Unit/DocumentServiceTest.php';

$tests = [
    'test_vendor_bill_posting_locks_and_logs',
    'test_document_attachment_inherits_company_and_logs',
];

foreach ($tests as $test) {
    $test();
    echo "PASS: {$test}\n";
}
