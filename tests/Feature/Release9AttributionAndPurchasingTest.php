<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesDocumentResource;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchasePlan;
use App\Models\ReorderSuggestion;
use App\Models\ReorderSuggestionItem;
use App\Models\SalesDocument;
use App\Models\StoreLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\PurchasePlanService;
use App\Services\ReportService;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Release9AttributionAndPurchasingTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(string $role = 'staff'): array
    {
        $this->seed();
        $company = Company::first();
        $user = User::first();
        $user->syncRoles([$role]);
        $this->actingAs($user);
        $this->withSession([CompanyContext::SESSION_KEY => $company->id]);

        return compact('company', 'user');
    }

    public function test_store_scoping_staff_vs_manager(): void
    {
        ['company' => $company, 'user' => $user] = $this->setupContext('staff');
        $store1 = StoreLocation::create(['company_id' => $company->id, 'name' => 'Store A']);
        $store2 = StoreLocation::create(['company_id' => $company->id, 'name' => 'Store B']);
        $user->storeLocations()->sync([$store1->id]);

        SalesDocument::create(['company_id' => $company->id, 'store_location_id' => $store1->id, 'doc_type' => 'ticket', 'series' => 'T', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 10, 'tax_total' => 0.7, 'gross_total' => 10.7, 'source' => 'manual']);
        SalesDocument::create(['company_id' => $company->id, 'store_location_id' => $store2->id, 'doc_type' => 'ticket', 'series' => 'T', 'status' => 'posted', 'issue_date' => now(), 'net_total' => 20, 'tax_total' => 1.4, 'gross_total' => 21.4, 'source' => 'manual']);

        $this->assertCount(1, SalesDocumentResource::getEloquentQuery()->get());

        $user->syncRoles(['manager']);
        $this->assertCount(2, SalesDocumentResource::getEloquentQuery()->get());
    }



    public function test_store_location_required_for_non_manager_create(): void
    {
        $this->setupContext('staff');

        $salesPage = new class extends \App\Filament\Resources\SalesDocumentResource\Pages\CreateSalesDocument {
            public function runMutate(array $data): array { return $this->mutateFormDataBeforeCreate($data); }
        };
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $salesPage->runMutate(['doc_type' => 'ticket']);
    }

    public function test_repair_technician_assignment_works(): void
    {
        ['company' => $company] = $this->setupContext('manager');
        $tech = User::factory()->create();
        $store = StoreLocation::create(['company_id' => $company->id, 'name' => 'Main']);

        $repair = \App\Models\Repair::create([
            'company_id' => $company->id,
            'store_location_id' => $store->id,
            'customer_id' => null,
            'technician_user_id' => $tech->id,
            'status' => 'intake',
        ]);

        $this->assertEquals($tech->id, $repair->technician?->id);
    }

    public function test_kpi_breakdown_includes_store_and_user_buckets(): void
    {
        ['company' => $company, 'user' => $user] = $this->setupContext('manager');
        $store = StoreLocation::create(['company_id' => $company->id, 'name' => 'Main']);

        SalesDocument::create([
            'company_id' => $company->id,
            'store_location_id' => $store->id,
            'created_by_user_id' => $user->id,
            'doc_type' => 'invoice',
            'series' => 'F',
            'status' => 'posted',
            'issue_date' => now(),
            'net_total' => 100,
            'tax_total' => 7,
            'gross_total' => 107,
            'source' => 'manual',
        ]);

        $metrics = app(ReportService::class)->computeMetrics($company->id, now()->subDay(), now()->addDay());
        $this->assertNotEmpty($metrics['breakdown_by_store']);
        $this->assertNotEmpty($metrics['breakdown_by_user']);
    }

    public function test_purchase_plan_creation_and_status_transitions(): void
    {
        ['company' => $company] = $this->setupContext('manager');
        $product = Product::create(['name' => 'Stock', 'product_type' => 'stock']);
        $suggestion = ReorderSuggestion::create(['company_id' => $company->id, 'generated_at' => now(), 'period_days' => 30, 'from_date' => now()->subDays(30), 'to_date' => now(), 'payload' => [], 'created_at' => now()]);
        $item = ReorderSuggestionItem::create(['reorder_suggestion_id' => $suggestion->id, 'product_id' => $product->id, 'suggested_qty' => 5, 'days_cover_target' => 14, 'avg_daily_sold' => 1, 'on_hand' => 0, 'reason' => 'Need stock', 'created_at' => now()]);

        $service = app(PurchasePlanService::class);
        $plan = $service->createFromSuggestionItems($company->id, [$item->id]);
        $this->assertEquals('draft', $plan->status);

        $plan = $service->markOrdered($plan);
        $this->assertEquals('ordered', $plan->status);

        $plan = $service->receiveItem($plan, $plan->items->first()->id, 5);
        $this->assertEquals('received', $plan->status);
    }

    public function test_vendor_bill_linkage_updates_received_quantities(): void
    {
        ['company' => $company] = $this->setupContext('manager');
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'S']);
        $product = Product::create(['name' => 'Stock', 'product_type' => 'stock']);

        $plan = PurchasePlan::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'status' => 'ordered', 'planned_at' => now(), 'ordered_at' => now()]);
        $planItem = $plan->items()->create(['product_id' => $product->id, 'suggested_qty' => 10, 'ordered_qty' => 10, 'received_qty' => 0, 'status' => 'ordered']);

        $bill = VendorBill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'purchase_plan_id' => $plan->id, 'invoice_number' => 'INV-1', 'invoice_date' => now()->toDateString(), 'status' => 'posted']);
        $bill->lines()->create(['company_id' => $company->id, 'product_id' => $product->id, 'description' => 'Line', 'quantity' => 4, 'unit_price' => 1, 'net_amount' => 4, 'tax_amount' => 0, 'gross_amount' => 4]);

        app(PurchasePlanService::class)->syncReceivedFromVendorBill($bill->fresh('lines'));

        $planItem->refresh();
        $this->assertEquals(4.0, (float) $planItem->received_qty);
        $this->assertEquals('ordered', $planItem->status);
    }
}
