<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Documents/DocumentService.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Documents\DocumentService;

function test_document_attachment_inherits_company_and_logs(): void
{
    $audit = new AuditLogger();
    $service = new DocumentService($audit);

    $document = $service->attach(
        documentable: ['id' => 55, 'type' => 'vendor_bill', 'company_id' => 7],
        documentInput: [
            'path' => 'bills/2026/01/invoice-44.pdf',
            'original_name' => 'invoice-44.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 102400,
            'supplier_id' => 12,
        ],
        userId: 42
    );

    assert($document['company_id'] === 7);
    assert($document['documentable_type'] === 'vendor_bill');

    $events = $audit->all();
    assert(count($events) === 1);
    assert($events[0]['action'] === 'document.attached');
}
