<?php

namespace App\Domain\Integrations;

use App\Domain\Sales\SalesDocumentService;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\IntegrationRun;
use App\Models\Product;
use App\Models\SalesDocument;
use Illuminate\Support\Arr;

final class PrestaShopIngestService
{
    /** @param array<string,mixed> $payload */
    public function ingest(array $payload, int $companyId, bool $autoPost = false): SalesDocument
    {
        $hash = hash('sha256', json_encode($payload));

        try {
            $customerData = (array) ($payload['customer'] ?? []);
            $customer = Customer::query()->firstOrCreate(
                ['email' => $customerData['email'] ?? null],
                ['name' => $customerData['name'] ?? 'PrestaShop Customer', 'phone' => $customerData['phone'] ?? null]
            );

            $cc = CustomerCompany::query()->firstOrCreate(
                ['company_id' => $companyId, 'customer_id' => $customer->id],
                ['wants_full_invoice' => false]
            );

            $docType = $cc->wants_full_invoice ? 'invoice' : 'ticket';
            $series = $docType === 'invoice' ? 'F' : 'T';

            $document = SalesDocument::query()->create([
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'doc_type' => $docType,
                'series' => $series,
                'status' => 'draft',
                'issue_date' => now(),
                'source' => 'prestashop',
                'source_ref' => (string) ($payload['order_id'] ?? $payload['order_reference'] ?? ''),
                'created_by_user_id' => auth()->id(),
            ]);

            $lineNo = 1;
            foreach ((array) ($payload['lines'] ?? []) as $line) {
                $sku = Arr::get($line, 'sku') ?: Arr::get($line, 'ref');
                $barcode = Arr::get($line, 'ean');

                $product = Product::query()
                    ->where('sku', $sku)
                    ->orWhere('barcode', $barcode)
                    ->first();

                if (! $product) {
                    $product = Product::query()->create([
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'name' => Arr::get($line, 'name', 'PrestaShop Item'),
                        'product_type' => 'service',
                        'is_active' => true,
                    ]);
                }

                $qty = (float) Arr::get($line, 'qty', 1);
                $unit = (float) Arr::get($line, 'unit_price', 0);
                $taxRate = (float) Arr::get($line, 'tax_rate', 7);
                $net = round($qty * $unit, 2);
                $tax = round($net * ($taxRate / 100), 2);
                $gross = round($net + $tax, 2);

                $document->lines()->create([
                    'line_no' => $lineNo++,
                    'product_id' => $product->id,
                    'description' => Arr::get($line, 'name', 'Item'),
                    'qty' => $qty,
                    'unit_price' => $unit,
                    'tax_rate' => $taxRate,
                    'line_net' => $net,
                    'line_tax' => $tax,
                    'line_gross' => $gross,
                ]);
            }

            if ($autoPost) {
                app(SalesDocumentService::class)->post($document);
            }

            IntegrationRun::query()->create([
                'company_id' => $companyId,
                'integration' => 'prestashop',
                'action' => 'order-paid',
                'payload_hash' => $hash,
                'status' => 'success',
                'result_payload' => ['sales_document_id' => $document->id, 'status' => $document->fresh()->status],
            ]);

            return $document->fresh();
        } catch (\Throwable $e) {
            IntegrationRun::query()->create([
                'company_id' => $companyId,
                'integration' => 'prestashop',
                'action' => 'order-paid',
                'payload_hash' => $hash,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
