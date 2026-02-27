<?php

namespace Tests\Feature;

use App\Domain\Integrations\PrestaShopIngestService;
use App\Domain\Sales\SalesDocumentService;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\IntegrationApiToken;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\SalesDocument;
use App\Models\Warehouse;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Release3SalesTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $this->seed();
        $company = Company::first();
        $user = \App\Models\User::first();
        $this->actingAs($user);
        $this->withSession([CompanyContext::SESSION_KEY => $company->id]);

        Warehouse::firstOrCreate(['company_id' => $company->id, 'code' => 'MAIN'], ['name' => 'Main Warehouse', 'is_default' => true]);
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'MAIN')->first();
        Location::firstOrCreate(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'code' => 'DEF'], ['name' => 'Default', 'is_default' => true]);

        return compact('company', 'user', 'warehouse');
    }

    public function test_posting_assigns_sequential_numbers_per_company_series(): void
    {
        ['company' => $company] = $this->setupContext();
        $service = app(SalesDocumentService::class);

        $doc1 = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'ticket', 'series' => 'T', 'status' => 'draft', 'issue_date' => now(), 'source' => 'manual']);
        $doc1->lines()->create(['line_no' => 1, 'description' => 'A', 'qty' => 1, 'unit_price' => 10, 'tax_rate' => 7, 'line_net' => 10, 'line_tax' => 0.7, 'line_gross' => 10.7]);
        $service->post($doc1);

        $doc2 = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'ticket', 'series' => 'T', 'status' => 'draft', 'issue_date' => now(), 'source' => 'manual']);
        $doc2->lines()->create(['line_no' => 1, 'description' => 'B', 'qty' => 1, 'unit_price' => 12, 'tax_rate' => 7, 'line_net' => 12, 'line_tax' => 0.84, 'line_gross' => 12.84]);
        $service->post($doc2);

        $this->assertEquals(1, $doc1->fresh()->number);
        $this->assertEquals(2, $doc2->fresh()->number);
    }

    public function test_posting_ticket_creates_sale_stock_move_and_cost_capture(): void
    {
        ['company' => $company] = $this->setupContext();
        ProductCost::create(['company_id' => $company->id, 'product_id' => Product::create(['name' => 'Cable', 'product_type' => 'stock'])->id, 'avg_cost' => 5]);
        $productId = Product::where('name', 'Cable')->value('id');
        $doc = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'ticket', 'series' => 'T', 'status' => 'draft', 'issue_date' => now(), 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'product_id' => $productId, 'description' => 'Cable', 'qty' => 2, 'unit_price' => 12, 'tax_rate' => 7, 'line_net' => 24, 'line_tax' => 1.68, 'line_gross' => 25.68]);

        app(SalesDocumentService::class)->post($doc);

        $this->assertDatabaseHas('stock_moves', ['company_id' => $company->id, 'product_id' => $productId, 'move_type' => 'sale', 'qty' => -2.000]);
        $line = $doc->fresh()->lines->first();
        $this->assertEquals(5.0, (float) $line->cost_unit);
    }

    public function test_posting_credit_note_creates_return_in_stock_move(): void
    {
        ['company' => $company] = $this->setupContext();
        $product = Product::create(['name' => 'Router', 'product_type' => 'stock']);
        ProductCost::create(['company_id' => $company->id, 'product_id' => $product->id, 'avg_cost' => 8]);

        $invoice = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'draft', 'issue_date' => now(), 'source' => 'manual']);
        $invoice->lines()->create(['line_no' => 1, 'product_id' => $product->id, 'description' => 'Router', 'qty' => 1, 'unit_price' => 20, 'tax_rate' => 7, 'line_net' => 20, 'line_tax' => 1.4, 'line_gross' => 21.4]);
        $service = app(SalesDocumentService::class);
        $service->post($invoice);

        $credit = $service->createCreditNote($invoice->fresh());
        $service->post($credit);

        $this->assertDatabaseHas('stock_moves', ['company_id' => $company->id, 'product_id' => $product->id, 'move_type' => 'return_in', 'qty' => 1.000]);
    }

    public function test_prestashop_ingest_creates_draft_document_and_maps_entities(): void
    {
        ['company' => $company] = $this->setupContext();
        $token = 'secret-token';
        IntegrationApiToken::create(['company_id' => $company->id, 'name' => 'ps', 'token_hash' => hash('sha256', $token), 'is_active' => true]);

        $payload = [
            'order_id' => '1001',
            'order_reference' => 'PS-1001',
            'customer' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'lines' => [
                ['sku' => 'SKU-1', 'name' => 'Service plan', 'qty' => 1, 'unit_price' => 30, 'tax_rate' => 7],
            ],
            'totals' => ['gross' => 32.1],
            'paid_at' => now()->toISOString(),
        ];

        $res = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/integrations/prestashop/order-paid', $payload);
        $res->assertStatus(201);

        $this->assertDatabaseHas('customers', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('sales_documents', ['source' => 'prestashop', 'source_ref' => '1001', 'status' => 'draft']);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-1']);
    }

    public function test_company_scoping_blocks_cross_company_posting(): void
    {
        ['company' => $company] = $this->setupContext();
        $other = Company::create(['name' => 'Other']);
        $doc = SalesDocument::create(['company_id' => $other->id, 'doc_type' => 'ticket', 'series' => 'T', 'status' => 'draft', 'issue_date' => now(), 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'description' => 'X', 'qty' => 1, 'unit_price' => 10, 'tax_rate' => 7, 'line_net' => 10, 'line_tax' => 0.7, 'line_gross' => 10.7]);

        $this->expectException(\RuntimeException::class);
        app(SalesDocumentService::class)->post($doc);
    }
}
