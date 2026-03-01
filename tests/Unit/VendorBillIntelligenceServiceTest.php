<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCompanyPricing;
use App\Models\Supplier;
use App\Models\VendorBill;
use App\Models\Warehouse;
use App\Services\VendorBillIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorBillIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_profit_margin_formula_and_syncs_stock_and_cost(): void
    {
        $company = Company::query()->create(['name' => 'ACME', 'purchase_tax_rate' => 7]);
        $supplier = Supplier::query()->create(['company_id' => $company->id, 'name' => 'Supplier']);
        $warehouse = Warehouse::query()->create(['company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);

        $bill = VendorBill::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'INV-1',
            'invoice_date' => now()->toDateString(),
            'status' => 'posted',
            'receiving_warehouse_id' => $warehouse->id,
        ]);

        $line = $bill->lines()->create([
            'company_id' => $company->id,
            'ean' => '1234567890123',
            'description' => 'Cable',
            'quantity' => 2,
            'unit_price' => 10,
            'tax_rate' => 7,
            'margin_percent' => 20,
            'is_stock_item' => true,
        ]);

        app(VendorBillIntelligenceService::class)->processPostedBill($bill->fresh('lines'));

        $line = $line->fresh();
        $product = Product::query()->where('ean', '1234567890123')->firstOrFail();

        $this->assertSame($product->id, $line->product_id);
        $this->assertSame(10.0, (float) $product->cost);
        $this->assertSame('13.38', number_format((float) $line->suggested_net_sale_price, 2, '.', ''));
        $this->assertDatabaseHas('product_warehouse_stock', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('product_company_pricing', [
            'product_id' => $product->id,
            'company_id' => $company->id,
            'margin_percent' => 20,
        ]);
    }

    public function test_margin_fallback_chain_uses_company_override_then_product_default(): void
    {
        $company = Company::query()->create(['name' => 'ACME', 'purchase_tax_rate' => 7]);
        $supplier = Supplier::query()->create(['company_id' => $company->id, 'name' => 'Supplier']);
        $warehouse = Warehouse::query()->create(['company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);

        $product = Product::query()->create([
            'name' => 'Adapter',
            'ean' => '9876543210123',
            'product_type' => 'stock',
            'cost' => 8,
            'default_margin_percent' => 30,
            'is_active' => true,
        ]);

        ProductCompanyPricing::query()->create([
            'product_id' => $product->id,
            'company_id' => $company->id,
            'margin_percent' => 40,
        ]);

        $bill = VendorBill::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'INV-2',
            'invoice_date' => now()->toDateString(),
            'status' => 'posted',
            'receiving_warehouse_id' => $warehouse->id,
        ]);

        $line = $bill->lines()->create([
            'company_id' => $company->id,
            'ean' => '9876543210123',
            'description' => 'Adapter',
            'quantity' => 1,
            'unit_price' => 10,
            'tax_rate' => 7,
            'is_stock_item' => true,
        ]);

        app(VendorBillIntelligenceService::class)->processPostedBill($bill->fresh('lines'));
        $line = $line->fresh();

        $this->assertSame('17.83', number_format((float) $line->suggested_net_sale_price, 2, '.', ''));
    }
}
