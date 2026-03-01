<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCompanyPricing;
use App\Services\SalesPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_selling_net_uses_profit_margin_formula_with_company_override(): void
    {
        $company = Company::query()->create([
            'name' => 'ACME',
            'purchase_tax_rate' => 7,
        ]);

        $product = Product::query()->create([
            'name' => 'Cable',
            'product_type' => 'stock',
            'cost' => 10,
            'default_margin_percent' => 20,
            'is_active' => true,
        ]);

        ProductCompanyPricing::query()->create([
            'product_id' => $product->id,
            'company_id' => $company->id,
            'margin_percent' => 40,
        ]);

        $sellingNet = app(SalesPricingService::class)->calculateSellingNet($product, $company);

        // landed = 10 * 1.07 = 10.70; selling_net = 10.70 / (1 - 0.40) = 17.83
        $this->assertSame(17.83, $sellingNet);
    }

    public function test_enforce_minimum_price_throws_when_below_landed_cost(): void
    {
        $this->expectException(\RuntimeException::class);

        app(SalesPricingService::class)->enforceMinimumPrice(9.99, 10.00);
    }
}
