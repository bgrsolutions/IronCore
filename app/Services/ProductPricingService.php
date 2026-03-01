<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;

class ProductPricingService
{
    public function calculateSalePrice(Product $product, Company $company): float
    {
        $cost = (float) ($product->cost ?? 0);
        $marginPercent = $this->resolveMarginPercent($product, $company);

        return round($cost * (1 + ($marginPercent / 100)), 4);
    }

    public function applyVendorBillCostUpdate(Product $product, float $newCost, ?float $lineMarkupPercent = null): Product
    {
        $product->cost = $newCost;

        if ($lineMarkupPercent !== null) {
            $product->default_margin_percent = $lineMarkupPercent;
        }

        $product->save();

        return $product->refresh();
    }

    public function resolveMarginPercent(Product $product, Company $company): float
    {
        $override = $product->companyPricings()
            ->where('company_id', $company->id)
            ->value('margin_percent');

        return (float) ($override ?? $product->default_margin_percent ?? 0);
    }
}
