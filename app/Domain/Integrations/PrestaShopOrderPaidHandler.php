<?php

namespace App\Domain\Integrations;

use App\Domain\Sales\InvoiceService;

final class PrestaShopOrderPaidHandler
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    /**
     * @param array<string, mixed> $company
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(array $company, array $payload, int $userId): array
    {
        $seriesType = !empty($payload['customer_tax_id']) ? 'F' : 'T';

        $draft = $this->invoiceService->createDraft($company, [
            'id' => (int) $payload['order_id'],
            'series_type' => $seriesType,
            'customer_reference' => $payload['customer_id'] ?? null,
            'net_total' => (float) ($payload['net_total'] ?? 0),
            'tax_total' => (float) ($payload['tax_total'] ?? 0),
            'gross_total' => (float) ($payload['gross_total'] ?? 0),
            'source' => 'prestashop',
            'source_order_reference' => (string) ($payload['order_reference'] ?? ''),
        ], $userId);

        return $this->invoiceService->post($company, $draft, $userId);
    }
}
