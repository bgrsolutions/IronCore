<?php

namespace Tests\Feature;

use App\Domain\Inventory\StockService;
use App\Domain\Inventory\VendorBillStockIntegrationService;
use App\Models\Company;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockMove;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use App\Models\Warehouse;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): array
    {
        $this->seed();
        $company = Company::first();
        $user = User::first();
        $this->actingAs($user);
        $this->withSession([CompanyContext::SESSION_KEY => $company->id]);

        $warehouse = Warehouse::firstOrCreate(['company_id' => $company->id, 'code' => 'MAIN'], ['name' => 'Main', 'is_default' => true]);
        $location = Location::firstOrCreate(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'code' => 'DEF'], ['name' => 'Default', 'is_default' => true]);
        $product = Product::create(['name' => 'Cable', 'product_type' => 'stock']);

        return compact('company', 'user', 'warehouse', 'location', 'product');
    }

    public function test_posting_receipt_move_updates_avg_cost(): void
    {
        ['company' => $company, 'warehouse' => $warehouse, 'location' => $location, 'product' => $product] = $this->prepare();
        $service = app(StockService::class);

        $service->postMove([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'move_type' => 'receipt',
            'qty' => 10,
            'unit_cost' => 4,
            'occurred_at' => now(),
        ]);

        $cost = ProductCost::where('company_id', $company->id)->where('product_id', $product->id)->first();
        $this->assertEquals(4.0, (float) $cost->avg_cost);
    }

    public function test_posting_sale_uses_avg_cost_when_missing_unit_cost(): void
    {
        ['company' => $company, 'warehouse' => $warehouse, 'location' => $location, 'product' => $product] = $this->prepare();
        $service = app(StockService::class);

        $service->postMove(['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'location_id' => $location->id, 'move_type' => 'receipt', 'qty' => 5, 'unit_cost' => 6, 'occurred_at' => now()]);
        $out = $service->postMove(['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'location_id' => $location->id, 'move_type' => 'sale', 'qty' => -2, 'occurred_at' => now()]);

        $this->assertEquals(6.0, (float) $out->unit_cost);
        $this->assertEquals(12.0, (float) $out->total_cost);
    }

    public function test_on_hand_calculation_is_correct(): void
    {
        ['company' => $company, 'warehouse' => $warehouse, 'location' => $location, 'product' => $product] = $this->prepare();
        $service = app(StockService::class);
        $service->postMove(['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'location_id' => $location->id, 'move_type' => 'receipt', 'qty' => 8, 'unit_cost' => 3, 'occurred_at' => now()]);
        $service->postMove(['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'location_id' => $location->id, 'move_type' => 'adjustment_out', 'qty' => -3, 'occurred_at' => now()]);

        $this->assertEquals(5.0, $service->getOnHand($company->id, $product->id));
    }

    public function test_posting_vendor_bill_with_stock_lines_generates_receipt_moves(): void
    {
        ['company' => $company, 'product' => $product] = $this->prepare();

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp']);
        $bill = VendorBill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'INV-1', 'invoice_date' => now()->toDateString(), 'status' => 'posted']);
        VendorBillLine::create(['company_id' => $company->id, 'vendor_bill_id' => $bill->id, 'product_id' => $product->id, 'is_stock_item' => true, 'description' => 'stock', 'quantity' => 2, 'unit_price' => 9, 'net_amount' => 18, 'tax_amount' => 0, 'gross_amount' => 18]);

        app(VendorBillStockIntegrationService::class)->receiveForPostedBill($bill);

        $this->assertDatabaseHas('stock_moves', ['company_id' => $company->id, 'product_id' => $product->id, 'move_type' => 'receipt', 'reference_type' => 'vendor_bill_line']);
    }


    public function test_vendor_bill_receiving_is_idempotent(): void
    {
        ['company' => $company, 'product' => $product] = $this->prepare();

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp']);
        $bill = VendorBill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'INV-2', 'invoice_date' => now()->toDateString(), 'status' => 'posted']);
        VendorBillLine::create(['company_id' => $company->id, 'vendor_bill_id' => $bill->id, 'product_id' => $product->id, 'is_stock_item' => true, 'description' => 'stock', 'quantity' => 2, 'unit_price' => 9, 'net_amount' => 18, 'tax_amount' => 0, 'gross_amount' => 18]);

        app(VendorBillStockIntegrationService::class)->receiveForPostedBill($bill);
        app(VendorBillStockIntegrationService::class)->receiveForPostedBill($bill);

        $this->assertEquals(1, StockMove::query()->where('reference_type', 'vendor_bill_line')->count());
    }

    public function test_company_scoping_prevents_cross_company_stock_moves(): void
    {
        ['company' => $company, 'warehouse' => $warehouse, 'location' => $location, 'product' => $product] = $this->prepare();
        $other = Company::create(['name' => 'Other Co']);
        $otherWarehouse = Warehouse::create(['company_id' => $other->id, 'name' => 'Other', 'code' => 'O', 'is_default' => true]);

        $this->expectException(\RuntimeException::class);
        app(StockService::class)->postMove([
            'company_id' => $other->id,
            'product_id' => $product->id,
            'warehouse_id' => $otherWarehouse->id,
            'location_id' => null,
            'move_type' => 'receipt',
            'qty' => 1,
            'unit_cost' => 1,
            'occurred_at' => now(),
        ]);
    }
}
