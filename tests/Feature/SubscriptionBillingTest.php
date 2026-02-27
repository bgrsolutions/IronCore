<?php

namespace Tests\Feature;

use App\Domain\Billing\SubscriptionBillingService;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRun;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
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

    public function test_next_run_calculation_supports_3_6_12_months(): void
    {
        ['company' => $company] = $this->setupContext();
        $customer = Customer::create(['name' => 'A']);
        $svc = app(SubscriptionBillingService::class);

        foreach ([3, 6, 12] as $months) {
            $plan = SubscriptionPlan::create([
                'company_id' => $company->id,
                'name' => 'Plan '.$months,
                'interval_months' => $months,
                'price_net' => 10,
                'tax_rate' => 7,
            ]);
            $sub = Subscription::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'next_run_at' => now(),
            ]);

            $next = $svc->computeNextRun($sub, now());
            $this->assertEquals(now()->copy()->addMonthsNoOverflow($months)->format('Y-m-d'), $next->format('Y-m-d'));
        }
    }

    public function test_run_due_generates_sales_document_draft(): void
    {
        ['company' => $company] = $this->setupContext();
        $customer = Customer::create(['name' => 'B']);
        $plan = SubscriptionPlan::create(['company_id' => $company->id, 'name' => 'Draft plan', 'interval_months' => 3, 'price_net' => 30, 'tax_rate' => 7, 'auto_post' => false]);
        Subscription::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => now()->subMonths(3), 'next_run_at' => now()->subMinute()]);

        app(SubscriptionBillingService::class)->runDueSubscriptions($company->id);

        $this->assertDatabaseHas('sales_documents', ['company_id' => $company->id, 'status' => 'draft']);
        $this->assertDatabaseHas('subscription_runs', ['company_id' => $company->id, 'status' => 'success']);
    }

    public function test_subscription_auto_post_uses_same_posting_flow_for_inventory_moves(): void
    {
        ['company' => $company] = $this->setupContext();
        $customer = Customer::create(['name' => 'Inventory Customer']);
        $stock = Product::create(['name' => 'Stock subscription item', 'product_type' => 'stock']);

        $plan = SubscriptionPlan::create([
            'company_id' => $company->id,
            'name' => 'Inventory auto-post',
            'interval_months' => 3,
            'price_net' => 40,
            'tax_rate' => 7,
            'auto_post' => true,
        ]);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->subMonths(3),
            'next_run_at' => now()->subMinute(),
        ]);

        $subscription->items()->create([
            'product_id' => $stock->id,
            'description' => 'Stock line',
            'qty' => 1,
            'unit_price' => 40,
            'tax_rate' => 7,
        ]);

        app(SubscriptionBillingService::class)->runDueSubscriptions($company->id);

        $doc = \App\Models\SalesDocument::query()->latest('id')->first();
        $this->assertSame('posted', $doc->status);
        $this->assertDatabaseHas('stock_moves', [
            'company_id' => $company->id,
            'move_type' => 'sale',
            'reference_type' => 'sales_document_line',
        ]);
    }

    public function test_auto_post_posts_and_locks_document(): void
    {
        ['company' => $company] = $this->setupContext();
        $customer = Customer::create(['name' => 'C']);
        $plan = SubscriptionPlan::create(['company_id' => $company->id, 'name' => 'Auto post', 'interval_months' => 6, 'price_net' => 50, 'tax_rate' => 7, 'auto_post' => true]);
        Subscription::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => now()->subMonths(6), 'next_run_at' => now()->subMinute()]);

        app(SubscriptionBillingService::class)->runDueSubscriptions($company->id);

        $doc = \App\Models\SalesDocument::query()->latest('id')->first();
        $this->assertSame('posted', $doc->status);
        $this->assertNotNull($doc->locked_at);
    }

    public function test_paused_and_cancelled_are_skipped(): void
    {
        ['company' => $company] = $this->setupContext();
        $customer = Customer::create(['name' => 'D']);
        $plan = SubscriptionPlan::create(['company_id' => $company->id, 'name' => 'Skip', 'interval_months' => 12, 'price_net' => 10, 'tax_rate' => 7]);
        Subscription::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => 'paused', 'starts_at' => now()->subMonths(12), 'next_run_at' => now()->subMinute()]);
        Subscription::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => 'cancelled', 'starts_at' => now()->subMonths(12), 'next_run_at' => now()->subMinute()]);

        app(SubscriptionBillingService::class)->runDueSubscriptions($company->id);

        $this->assertDatabaseHas('subscription_runs', ['status' => 'skipped']);
    }

    public function test_subscription_runs_records_failure_and_multi_company_scope(): void
    {
        ['company' => $company] = $this->setupContext();
        $other = Company::create(['name' => 'Other']);

        $planMain = SubscriptionPlan::create(['company_id' => $company->id, 'name' => 'Main plan', 'interval_months' => 3, 'price_net' => 20, 'tax_rate' => 7]);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $planMain->id, 'starts_at' => now()->subMonths(3), 'next_run_at' => now()->subMinute()]);
        Subscription::create(['company_id' => $company->id, 'plan_id' => null, 'price_net' => null, 'tax_rate' => null, 'starts_at' => now()->subMonths(3), 'next_run_at' => now()->subMinute()]);

        $planOther = SubscriptionPlan::create(['company_id' => $other->id, 'name' => 'Other plan', 'interval_months' => 3, 'price_net' => 20, 'tax_rate' => 7]);
        Subscription::create(['company_id' => $other->id, 'plan_id' => $planOther->id, 'starts_at' => now()->subMonths(3), 'next_run_at' => now()->subMinute()]);

        app(SubscriptionBillingService::class)->runDueSubscriptions($company->id);

        $this->assertGreaterThan(0, SubscriptionRun::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('subscription_runs', ['company_id' => $company->id, 'status' => 'failed']);
        $this->assertEquals(0, SubscriptionRun::query()->where('company_id', $other->id)->count());
    }
}
