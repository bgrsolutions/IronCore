<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Sales/InvoiceSeriesService.php';
require_once __DIR__ . '/../../app/Domain/Sales/InvoiceService.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Sales\InvoiceSeriesService;
use App\Domain\Sales\InvoiceService;

function test_invoice_posting_assigns_series_number_and_locks(): void
{
    $audit = new AuditLogger();
    $service = new InvoiceService(new InvoiceSeriesService(), $audit);

    $company = ['id' => 1, 'invoice_prefix_t' => 'TIC', 'invoice_prefix_f' => 'FAC', 'invoice_prefix_nc' => 'NC'];

    $draft = $service->createDraft($company, ['id' => 77, 'series_type' => 'T', 'gross_total' => 100], 2);
    $posted = $service->post($company, $draft, 2);

    assert($posted['status'] === 'posted');
    assert($posted['number'] === 'TIC-000001');
    assert(!empty($posted['locked_at']));

    $blocked = false;
    try {
        $service->update($posted, ['gross_total' => 120], 2);
    } catch (RuntimeException) {
        $blocked = true;
    }

    assert($blocked === true);
}

function test_credit_note_is_created_against_posted_invoice(): void
{
    $audit = new AuditLogger();
    $service = new InvoiceService(new InvoiceSeriesService(), $audit);

    $company = ['id' => 2, 'invoice_prefix_t' => 'T', 'invoice_prefix_f' => 'F', 'invoice_prefix_nc' => 'NC'];

    $posted = $service->post($company, $service->createDraft($company, [
        'id' => 88,
        'series_type' => 'F',
        'net_total' => 100,
        'tax_total' => 7,
        'gross_total' => 107,
    ], 4), 4);

    $credit = $service->createCreditNote($company, $posted, 'Return accepted', 4);

    assert($credit['series_type'] === 'NC');
    assert($credit['gross_total'] === -107.0);
    assert($credit['credit_note_id'] === 88);
}
