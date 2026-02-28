<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\IntegrationApiToken;
use App\Models\Product;
use App\Models\ProductReorderSetting;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\SupplierProductCost;
use App\Models\SupplierStockSnapshot;
use App\Models\SupplierStockSnapshotItem;
use App\Models\Warehouse;
use App\Services\ReorderSuggestionService;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Release8PurchasingIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $this->seed();
        $company = Company::first();
        $user = \App\Models\User::first();
        $this->actingAs($user);
        $this->withSession([CompanyContext::SESSION_KEY => $company->id]);

        return compact('company', 'user');
    }

    public function test_avg_daily_sold_nets_credit_notes(): void
    {
        ['company' => $company] = $this->setupContext();

        $product = Product::create(['name' => 'Stock A', 'product_type' => 'stock']);
        ProductReorderSetting::create(['company_id' => $company->id, 'product_id' => $product->id, 'is_enabled' => true]);

        $invoice = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 100, 'tax_total' => 7, 'gross_total' => 107, 'source' => 'manual']);
        $invoice->lines()->create(['line_no' => 1, 'product_id' => $product->id, 'description' => 'Sale', 'qty' => 10, 'unit_price' => 10, 'tax_rate' => 7, 'line_net' => 100, 'line_tax' => 7, 'line_gross' => 107]);

        $credit = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'credit_note', 'series' => 'NC', 'status' => 'posted', 'issue_date' => now(), 'net_total' => -20, 'tax_total' => -1.4, 'gross_total' => -21.4, 'source' => 'manual']);
        $credit->lines()->create(['line_no' => 1, 'product_id' => $product->id, 'description' => 'Return', 'qty' => 2, 'unit_price' => -10, 'tax_rate' => 7, 'line_net' => -20, 'line_tax' => -1.4, 'line_gross' => -21.4]);

        $suggestion = app(ReorderSuggestionService::class)->generate($company->id, 30, auth()->id());
        $item = $suggestion->items()->where('product_id', $product->id)->first();

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(8 / 30, (float) $item->avg_daily_sold, 0.0002);
    }

    public function test_suggested_qty_respects_min_order_and_pack_rounding(): void
    {
        ['company' => $company] = $this->setupContext();
        $product = Product::create(['name' => 'Stock B', 'product_type' => 'stock']);

        ProductReorderSetting::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'is_enabled' => true,
            'lead_time_days' => 0,
            'safety_days' => 0,
            'min_days_cover' => 14,
            'min_order_qty' => 10,
            'pack_size_qty' => 6,
        ]);

        $doc = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 70, 'tax_total' => 4.9, 'gross_total' => 74.9, 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'product_id' => $product->id, 'description' => 'Sale', 'qty' => 3, 'unit_price' => 23.33, 'tax_rate' => 7, 'line_net' => 70, 'line_tax' => 4.9, 'line_gross' => 74.9]);

        $suggestion = app(ReorderSuggestionService::class)->generate($company->id, 30, auth()->id());
        $item = $suggestion->items()->where('product_id', $product->id)->first();

        $this->assertNotNull($item);
        $this->assertEquals(12.0, (float) $item->suggested_qty);
    }

    public function test_negative_exposure_sets_urgency_reason(): void
    {
        ['company' => $company] = $this->setupContext();
        $product = Product::create(['name' => 'Stock C', 'product_type' => 'stock']);
        ProductReorderSetting::create(['company_id' => $company->id, 'product_id' => $product->id, 'is_enabled' => true]);

        DB::table('stock_moves')->insert([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::query()->where('company_id', $company->id)->value('id'),
            'move_type' => 'sale',
            'qty' => -5,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $doc = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 50, 'tax_total' => 3.5, 'gross_total' => 53.5, 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'product_id' => $product->id, 'description' => 'Sale', 'qty' => 4, 'unit_price' => 12.5, 'tax_rate' => 7, 'line_net' => 50, 'line_tax' => 3.5, 'line_gross' => 53.5]);

        $suggestion = app(ReorderSuggestionService::class)->generate($company->id, 30, auth()->id());
        $item = $suggestion->items()->where('product_id', $product->id)->first();

        $this->assertNotNull($item);
        $this->assertEquals(5.0, (float) $item->negative_exposure);
        $this->assertStringContainsString('Urgent', $item->reason);
    }

    public function test_supplier_available_uses_latest_snapshot(): void
    {
        ['company' => $company] = $this->setupContext();
        $product = Product::create(['name' => 'Stock D', 'product_type' => 'stock']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp']);

        ProductReorderSetting::create(['company_id' => $company->id, 'product_id' => $product->id, 'is_enabled' => true, 'preferred_supplier_id' => $supplier->id]);

        $old = SupplierStockSnapshot::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'warehouse_name' => 'W1', 'snapshot_at' => now()->subDay(), 'source' => 'import', 'created_at' => now()]);
        SupplierStockSnapshotItem::create(['supplier_stock_snapshot_id' => $old->id, 'product_id' => $product->id, 'qty_available' => 2, 'created_at' => now()]);

        $new = SupplierStockSnapshot::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'warehouse_name' => 'W2', 'snapshot_at' => now(), 'source' => 'import', 'created_at' => now()]);
        SupplierStockSnapshotItem::create(['supplier_stock_snapshot_id' => $new->id, 'product_id' => $product->id, 'qty_available' => 8, 'created_at' => now()]);

        $doc = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 50, 'tax_total' => 3.5, 'gross_total' => 53.5, 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'product_id' => $product->id, 'description' => 'Sale', 'qty' => 2, 'unit_price' => 25, 'tax_rate' => 7, 'line_net' => 50, 'line_tax' => 3.5, 'line_gross' => 53.5]);

        $suggestion = app(ReorderSuggestionService::class)->generate($company->id, 30, auth()->id());
        $item = $suggestion->items()->where('product_id', $product->id)->first();

        $this->assertEquals(8.0, (float) $item->supplier_available);
    }

    public function test_estimated_spend_uses_supplier_product_cost_when_present(): void
    {
        ['company' => $company] = $this->setupContext();
        $product = Product::create(['name' => 'Stock E', 'product_type' => 'stock']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp 2']);

        ProductReorderSetting::create(['company_id' => $company->id, 'product_id' => $product->id, 'is_enabled' => true, 'preferred_supplier_id' => $supplier->id]);
        SupplierProductCost::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'product_id' => $product->id, 'last_unit_cost' => 9.5, 'currency' => 'EUR', 'last_seen_at' => now()]);

        $doc = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 100, 'tax_total' => 7, 'gross_total' => 107, 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'product_id' => $product->id, 'description' => 'Sale', 'qty' => 5, 'unit_price' => 20, 'tax_rate' => 7, 'line_net' => 100, 'line_tax' => 7, 'line_gross' => 107]);

        $suggestion = app(ReorderSuggestionService::class)->generate($company->id, 30, auth()->id());
        $item = $suggestion->items()->where('product_id', $product->id)->first();

        $this->assertNotNull($item->estimated_spend);
        $this->assertEquals(round((float) $item->suggested_qty * 9.5, 2), (float) $item->estimated_spend);
    }

    public function test_supplier_stock_import_api_creates_snapshot_and_matches_by_barcode(): void
    {
        ['company' => $company] = $this->setupContext();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp 3']);
        $product = Product::create(['name' => 'Match Product', 'product_type' => 'stock', 'barcode' => 'BAR-123']);

        $token = 'supplier-stock-token';
        IntegrationApiToken::create([
            'company_id' => $company->id,
            'name' => 'supplier-stock',
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'warehouse_name' => 'Remote-1',
            'items' => [
                [
                    'barcode' => 'BAR-123',
                    'supplier_sku' => 'SUP-1',
                    'product_name' => 'Matched',
                    'qty_available' => 12,
                    'unit_cost' => 4.2,
                    'currency' => 'EUR',
                ],
            ],
        ];

        $res = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/integrations/supplier-stock/import', $payload);
        $res->assertStatus(201);

        $snapshotId = $res->json('snapshot_id');
        $this->assertNotNull($snapshotId);
        $this->assertDatabaseHas('supplier_stock_snapshots', ['id' => $snapshotId, 'supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('supplier_stock_snapshot_items', ['supplier_stock_snapshot_id' => $snapshotId, 'product_id' => $product->id, 'barcode' => 'BAR-123']);
    }
}
