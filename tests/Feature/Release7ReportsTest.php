<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ReportSnapshot;
use App\Models\SalesDocument;
use App\Models\StockMove;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Warehouse;
use App\Services\ReportService;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Release7ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): Company
    {
        $this->seed();
        $company = Company::first();
        $user = \App\Models\User::first();
        $this->actingAs($user);
        $this->withSession([CompanyContext::SESSION_KEY => $company->id]);

        return $company;
    }

    public function test_snapshot_generation_is_idempotent(): void
    {
        $company = $this->setupContext();
        $svc = app(ReportService::class);
        $date = now()->toDateString();

        $svc->generateDailySnapshot($company->id, now());
        $svc->generateDailySnapshot($company->id, now());

        $this->assertEquals(1, ReportSnapshot::query()->where('company_id', $company->id)->where('snapshot_type', 'daily')->whereDate('snapshot_date', $date)->count());
    }

    public function test_gross_margin_computation_fixture(): void
    {
        $company = $this->setupContext();
        $doc = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 100, 'tax_total' => 7, 'gross_total' => 107, 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'description' => 'A', 'qty' => 1, 'unit_price' => 100, 'tax_rate' => 7, 'line_net' => 100, 'line_tax' => 7, 'line_gross' => 107, 'cost_total' => 60]);

        $metrics = app(ReportService::class)->computeMetrics($company->id, now()->subDay(), now()->addDay());

        $this->assertEquals(100.0, (float) $metrics['sales']['revenue_net']);
        $this->assertEquals(60.0, (float) $metrics['sales']['cogs_total']);
        $this->assertEquals(40.0, (float) $metrics['sales']['gross_profit']);
    }

    public function test_stock_value_computation_uses_avg_cost_and_ignores_negative_on_hand(): void
    {
        $company = $this->setupContext();
        $product = Product::create(['name' => 'Stock P', 'product_type' => 'stock']);
        ProductCost::create(['company_id' => $company->id, 'product_id' => $product->id, 'avg_cost' => 10]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'W', 'code' => 'W1', 'is_default' => true]);
        StockMove::create(['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'move_type' => 'receipt', 'qty' => 5, 'unit_cost' => 10, 'total_cost' => 50, 'occurred_at' => now()]);
        StockMove::create(['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'move_type' => 'sale', 'qty' => -8, 'unit_cost' => 10, 'total_cost' => 80, 'occurred_at' => now()]);

        $metrics = app(ReportService::class)->computeMetrics($company->id, now()->subDay(), now()->addDay());

        $this->assertEquals(0.0, (float) $metrics['inventory']['stock_value']);
    }



    public function test_credit_note_cogs_are_netted_by_document_type_with_positive_cost_storage(): void
    {
        $company = $this->setupContext();

        $invoice = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 100, 'tax_total' => 7, 'gross_total' => 107, 'source' => 'manual']);
        $invoice->lines()->create(['line_no' => 1, 'description' => 'Main sale', 'qty' => 1, 'unit_price' => 100, 'tax_rate' => 7, 'line_net' => 100, 'line_tax' => 7, 'line_gross' => 107, 'cost_total' => 60]);

        $credit = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'credit_note', 'series' => 'NC', 'status' => 'posted', 'issue_date' => now(), 'net_total' => -30, 'tax_total' => -2.1, 'gross_total' => -32.1, 'source' => 'manual']);
        $credit->lines()->create(['line_no' => 1, 'description' => 'Refund', 'qty' => 1, 'unit_price' => -30, 'tax_rate' => 7, 'line_net' => -30, 'line_tax' => -2.1, 'line_gross' => -32.1, 'cost_total' => 20]);

        $metrics = app(ReportService::class)->computeMetrics($company->id, now()->subDay(), now()->addDay());

        $this->assertEquals(40.0, (float) $metrics['sales']['cogs_total']);
    }

    public function test_mrr_normalization_for_3_6_12_intervals(): void
    {
        $company = $this->setupContext();
        foreach ([[3, 30], [6, 60], [12, 120]] as [$interval, $price]) {
            $plan = SubscriptionPlan::create(['company_id' => $company->id, 'name' => 'P'.$interval, 'interval_months' => $interval, 'price_net' => $price, 'tax_rate' => 7]);
            Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'next_run_at' => now()->addDays(5)]);
        }

        $metrics = app(ReportService::class)->computeMetrics($company->id, now()->subDay(), now()->addDay());

        $this->assertEquals(30.0, (float) $metrics['subscriptions']['mrr_estimate']);
    }

    public function test_negative_margin_document_detection(): void
    {
        $company = $this->setupContext();
        $doc = SalesDocument::create(['company_id' => $company->id, 'doc_type' => 'invoice', 'series' => 'F', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 50, 'tax_total' => 3.5, 'gross_total' => 53.5, 'source' => 'manual']);
        $doc->lines()->create(['line_no' => 1, 'description' => 'Loss', 'qty' => 1, 'unit_price' => 50, 'tax_rate' => 7, 'line_net' => 50, 'line_tax' => 3.5, 'line_gross' => 53.5, 'cost_total' => 70]);

        $metrics = app(ReportService::class)->computeMetrics($company->id, now()->subDay(), now()->addDay());

        $this->assertCount(1, $metrics['sales']['negative_margin_documents']);
    }
}
