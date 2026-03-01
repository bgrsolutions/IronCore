<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCompanyPricing;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_default_margin_when_override_is_missing(): void
    {
        $service = app(ProductPricingService::class);

        $company = Company::query()->create(['name' => 'Acme']);
        $product = Product::query()->create([
            'name' => 'Cable',
            'product_type' => 'stock',
            'cost' => 10,
            'default_margin_percent' => 25,
        ]);

        $this->assertSame(25.0, $service->resolveMarginPercent($product, $company));
        $this->assertSame(12.5, $service->calculateSalePrice($product, $company));
    }

    public function test_it_prefers_company_override_margin(): void
    {
        $service = app(ProductPricingService::class);

        $company = Company::query()->create(['name' => 'Acme']);
        $product = Product::query()->create([
            'name' => 'Cable',
            'product_type' => 'stock',
            'cost' => 10,
            'default_margin_percent' => 25,
        ]);

        ProductCompanyPricing::query()->create([
            'product_id' => $product->id,
            'company_id' => $company->id,
            'margin_percent' => 40,
        ]);

        $this->assertSame(40.0, $service->resolveMarginPercent($product, $company));
        $this->assertSame(14.0, $service->calculateSalePrice($product, $company));
    }
}
