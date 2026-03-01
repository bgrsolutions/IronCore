<?php

namespace App\Services;

use App\Domain\Inventory\StockService;
use App\Models\Company;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCompanyPricing;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use App\Models\Warehouse;
use RuntimeException;

class VendorBillIntelligenceService
{
    public function __construct(
        private readonly ProductPricingService $productPricingService,
        private readonly StockService $stockService
    ) {}

    public function processPostedBill(VendorBill $bill): void
    {
        $company = Company::query()->findOrFail((int) $bill->company_id);
        $warehouse = $this->resolveReceivingWarehouse($bill);
        $locationId = Location::query()
            ->where('company_id', $bill->company_id)
            ->where('warehouse_id', $warehouse->id)
            ->where('is_default', true)
            ->value('id');

        foreach ($bill->lines()->lockForUpdate()->get() as $line) {
            $product = $this->resolveProduct($bill, $line);
            if (! $product) {
                continue;
            }

            $taxPercent = (float) ($line->tax_rate ?? $company->purchase_tax_rate ?? 0);
            $unitCost = (float) ($line->unit_price ?? 0);
            $lineNet = round((float) ($line->quantity ?? 0) * $unitCost, 2);
            $lineTax = round($lineNet * ($taxPercent / 100), 2);
            $lineGross = round($lineNet + $lineTax, 2);

            $marginPercent = $line->margin_percent !== null
                ? (float) $line->margin_percent
                : $this->productPricingService->resolveMarginPercent($product, $company);

            $suggestedNetSale = $this->calculateSuggestedNetSalePrice($unitCost, $taxPercent, $marginPercent);

            $line->update([
                'product_id' => $product->id,
                'description' => $line->description ?: $product->name,
                'net_amount' => $lineNet,
                'tax_rate' => $taxPercent,
                'tax_amount' => $lineTax,
                'gross_amount' => $lineGross,
                'suggested_net_sale_price' => $suggestedNetSale,
            ]);

            $this->productPricingService->applyVendorBillCostUpdate($product, $unitCost);

            if ($line->margin_percent !== null) {
                ProductCompanyPricing::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'company_id' => $bill->company_id,
                    ],
                    [
                        'margin_percent' => (float) $line->margin_percent,
                    ]
                );
            }

            if ((float) $line->quantity <= 0) {
                continue;
            }

            $alreadyReceived = $this->alreadyReceived($line);
            if ($alreadyReceived) {
                continue;
            }

            $this->stockService->postMove([
                'company_id' => $bill->company_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'location_id' => $locationId,
                'move_type' => 'receipt',
                'qty' => (float) $line->quantity,
                'unit_cost' => $unitCost,
                'reference_type' => 'vendor_bill_line',
                'reference_id' => $line->id,
                'note' => 'Received from vendor bill #'.$bill->id,
                'occurred_at' => now(),
            ]);
        }
    }

    public function calculateSuggestedNetSalePrice(float $unitCost, float $purchaseTaxPercent, float $marginPercent): float
    {
        if ($marginPercent >= 100) {
            throw new RuntimeException('Margin percent must be below 100.');
        }

        $landedCost = $unitCost * (1 + ($purchaseTaxPercent / 100));
        if ($landedCost <= 0) {
            return 0.0;
        }

        $divisor = 1 - ($marginPercent / 100);
        if ($divisor <= 0) {
            throw new RuntimeException('Margin percent must be below 100.');
        }

        return round($landedCost / $divisor, 2);
    }

    private function resolveReceivingWarehouse(VendorBill $bill): Warehouse
    {
        if ($bill->receiving_warehouse_id) {
            return Warehouse::query()->findOrFail((int) $bill->receiving_warehouse_id);
        }

        return Warehouse::query()->firstOrCreate(
            ['company_id' => $bill->company_id, 'is_default' => true],
            ['name' => 'Main Warehouse', 'code' => 'MAIN', 'is_default' => true]
        );
    }

    private function resolveProduct(VendorBill $bill, VendorBillLine $line): ?Product
    {
        if ($line->product_id) {
            return Product::query()->find((int) $line->product_id);
        }

        if ($line->ean) {
            $existing = Product::query()->where('ean', $line->ean)->first();
            if ($existing) {
                return $existing;
            }

            return Product::query()->create([
                'name' => $line->description ?: 'Vendor bill item',
                'ean' => $line->ean,
                'cost' => (float) ($line->unit_price ?? 0),
                'supplier_id' => $bill->supplier_id,
                'default_margin_percent' => 30,
                'category_id' => null,
                'product_type' => 'stock',
                'is_active' => true,
            ]);
        }

        return null;
    }

    private function alreadyReceived(VendorBillLine $line): bool
    {
        return \App\Models\StockMove::query()
            ->where('reference_type', 'vendor_bill_line')
            ->where('reference_id', $line->id)
            ->exists();
    }
}
