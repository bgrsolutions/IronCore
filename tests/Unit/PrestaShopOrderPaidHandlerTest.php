<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Sales/InvoiceSeriesService.php';
require_once __DIR__ . '/../../app/Domain/Sales/InvoiceService.php';
require_once __DIR__ . '/../../app/Domain/Integrations/PrestaShopOrderPaidHandler.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Integrations\PrestaShopOrderPaidHandler;
use App\Domain\Sales\InvoiceSeriesService;
use App\Domain\Sales\InvoiceService;

function test_prestashop_paid_order_posts_ticket_or_invoice(): void
{
    $service = new InvoiceService(new InvoiceSeriesService(), new AuditLogger());
    $handler = new PrestaShopOrderPaidHandler($service);

    $company = ['id' => 3, 'invoice_prefix_t' => 'T', 'invoice_prefix_f' => 'F', 'invoice_prefix_nc' => 'NC'];

    $ticket = $handler->handle($company, [
        'order_id' => 3001,
        'order_reference' => 'PS-3001',
        'net_total' => 50,
        'tax_total' => 3.5,
        'gross_total' => 53.5,
    ], 11);

    $invoice = $handler->handle($company, [
        'order_id' => 3002,
        'order_reference' => 'PS-3002',
        'customer_tax_id' => 'B12345678',
        'net_total' => 80,
        'tax_total' => 5.6,
        'gross_total' => 85.6,
    ], 11);

    assert($ticket['series_type'] === 'T');
    assert($ticket['number'] === 'T-000001');
    assert($invoice['series_type'] === 'F');
    assert($invoice['number'] === 'F-000001');
}
