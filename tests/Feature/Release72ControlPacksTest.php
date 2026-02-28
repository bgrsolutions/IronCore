<?php

namespace Tests\Feature;

use App\Domain\Inventory\VendorBillStockIntegrationService;
use App\Domain\Repairs\RepairWorkflowService;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\Repair;
use App\Models\Supplier;
use App\Models\SupplierProductCost;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use App\Models\Warehouse;
use App\Services\RepairMetricsService;
use App\Services\ReportService;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Release72ControlPacksTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $this->seed();
        $company = Company::first();
        $user = User::first();
        $this->actingAs($user);
        $this->withSession([CompanyContext::SESSION_KEY => $company->id]);

        Warehouse::firstOrCreate(['company_id' => $company->id, 'code' => 'MAIN'], ['name' => 'Main Warehouse', 'is_default' => true]);

        return compact('company', 'user');
    }

    public function test_warning_condition_triggers_for_time_leak(): void
    {
        ['company' => $company] = $this->setupContext();

        $repair = Repair::create(['company_id' => $company->id, 'status' => 'in_progress']);
        DB::table('repair_time_entries')->insert([
            'company_id' => $company->id,
            'repair_id' => $repair->id,
            'minutes' => 30,
            'labour_product_code' => 'LAB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(app(RepairWorkflowService::class)->isTimeLeakBlocked($repair->fresh()));
    }

    public function test_quick_action_adds_labour_line_with_expected_tax_price_logic(): void
    {
        ['company' => $company] = $this->setupContext();
        config(['repairs.labour_rate_per_hour_net' => 60.0, 'repairs.default_tax_rate' => 7.0]);

        $repair = Repair::create(['company_id' => $company->id, 'status' => 'in_progress']);
        app(RepairMetricsService::class)->addQuickLabourLine($repair, 30);

        $line = DB::table('repair_line_items')->where('repair_id', $repair->id)->first();
        $this->assertNotNull($line);
        $this->assertEquals('labour', $line->line_type);
        $this->assertEquals(0.5, (float) $line->qty);
        $this->assertEquals(60.0, (float) $line->unit_price);
        $this->assertEquals(7.0, (float) $line->tax_rate);
        $this->assertEquals(30.0, (float) $line->line_net);
    }

    public function test_staff_cannot_transition_ready_when_time_leak_blocked(): void
    {
        ['company' => $company] = $this->setupContext();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->companies()->syncWithoutDetaching([$company->id]);

        $repair = Repair::create(['company_id' => $company->id, 'status' => 'in_progress']);
        DB::table('repair_time_entries')->insert([
            'company_id' => $company->id,
            'repair_id' => $repair->id,
            'minutes' => 20,
            'labour_product_code' => 'LAB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Manager/admin override required');
        app(RepairWorkflowService::class)->transitionModel($repair, 'ready', $staff->id, '');
    }

    public function test_manager_override_requires_reason_and_is_audited(): void
    {
        ['company' => $company] = $this->setupContext();

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->companies()->syncWithoutDetaching([$company->id]);

        $repair = Repair::create(['company_id' => $company->id, 'status' => 'in_progress']);
        DB::table('repair_time_entries')->insert([
            'company_id' => $company->id,
            'repair_id' => $repair->id,
            'minutes' => 25,
            'labour_product_code' => 'LAB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(RepairWorkflowService::class)->transitionModel($repair, 'ready', $manager->id, '');
            $this->fail('Expected missing reason exception.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('reason is required', $e->getMessage());
        }

        $updated = app(RepairWorkflowService::class)->transitionModel($repair->fresh(), 'ready', $manager->id, 'Approved at no-charge');
        $this->assertSame('ready', $updated->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair_time_leak_override', 'auditable_id' => $repair->id]);
    }

    public function test_time_leak_flag_is_exposed_in_report_rows(): void
    {
        ['company' => $company] = $this->setupContext();

        $repair = Repair::create(['company_id' => $company->id, 'status' => 'in_progress']);
        DB::table('repair_time_entries')->insert([
            'company_id' => $company->id,
            'repair_id' => $repair->id,
            'minutes' => 22,
            'labour_product_code' => 'LAB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = app(ReportService::class)->repairProfitabilityRows($company->id);
        $row = collect($rows)->firstWhere('repair_id', $repair->id);

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['time_leak_flag']);
    }

    public function test_supplier_product_cost_upsert_and_drift_warning_flags_line_and_audit(): void
    {
        ['company' => $company] = $this->setupContext();

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp A']);
        $product = Product::create(['name' => 'Part A', 'product_type' => 'stock']);

        $bill1 = VendorBill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'B1', 'invoice_date' => now()->toDateString(), 'status' => 'posted']);
        $line1 = VendorBillLine::create(['company_id' => $company->id, 'vendor_bill_id' => $bill1->id, 'product_id' => $product->id, 'is_stock_item' => true, 'description' => 'p', 'quantity' => 1, 'unit_price' => 10, 'net_amount' => 10, 'tax_amount' => 0, 'gross_amount' => 10]);

        app(VendorBillStockIntegrationService::class)->receiveForPostedBill($bill1);

        $this->assertDatabaseHas('supplier_product_costs', ['company_id' => $company->id, 'supplier_id' => $supplier->id, 'product_id' => $product->id]);
        $this->assertFalse((bool) $line1->fresh()->cost_increase_flag);

        $bill2 = VendorBill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'B2', 'invoice_date' => now()->toDateString(), 'status' => 'posted']);
        $line2 = VendorBillLine::create(['company_id' => $company->id, 'vendor_bill_id' => $bill2->id, 'product_id' => $product->id, 'is_stock_item' => true, 'description' => 'p', 'quantity' => 1, 'unit_price' => 10.6, 'net_amount' => 10.6, 'tax_amount' => 0, 'gross_amount' => 10.6]);

        app(VendorBillStockIntegrationService::class)->receiveForPostedBill($bill2);

        $this->assertTrue((bool) $line2->fresh()->cost_increase_flag);
        $this->assertEquals(6.0, round((float) $line2->fresh()->cost_increase_percent, 1));
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier_cost_increase', 'auditable_id' => $bill2->id]);

        $cost = SupplierProductCost::query()->where('company_id', $company->id)->where('supplier_id', $supplier->id)->where('product_id', $product->id)->first();
        $this->assertEquals(10.6, (float) $cost->last_unit_cost);
    }

    public function test_dead_stock_rows_include_last_moved_and_on_hand_qty(): void
    {
        ['company' => $company] = $this->setupContext();

        $product = Product::create(['name' => 'Slow Item', 'product_type' => 'stock']);
        ProductCost::create(['company_id' => $company->id, 'product_id' => $product->id, 'avg_cost' => 5]);

        DB::table('stock_moves')->insert([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::where('company_id', $company->id)->value('id'),
            'move_type' => 'receipt',
            'qty' => 4,
            'unit_cost' => 5,
            'total_cost' => 20,
            'occurred_at' => now()->subDays(90),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = app(ReportService::class)->deadStockRows($company->id);
        $row = collect($rows)->firstWhere('product_id', $product->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('last_moved_at', $row);
        $this->assertArrayHasKey('on_hand_qty', $row);
    }
}
