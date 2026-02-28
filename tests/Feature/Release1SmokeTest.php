<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Release1SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_release1_smoke_flow_posts_vendor_bill_and_expense(): void
    {
        $this->seed();

        $company = Company::first();
        $user = User::first();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Proveedor Uno']);

        $bill = VendorBill::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'B-100',
            'invoice_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        VendorBillLine::create([
            'company_id' => $company->id,
            'vendor_bill_id' => $bill->id,
            'description' => 'Item',
            'quantity' => 1,
            'unit_price' => 100,
            'net_amount' => 100,
            'tax_amount' => 7,
            'gross_amount' => 107,
        ]);

        $totals = $bill->lines()->selectRaw('SUM(net_amount) net, SUM(tax_amount) tax, SUM(gross_amount) gross')->first();
        $bill->update(['status' => 'posted', 'net_total' => $totals->net, 'tax_total' => $totals->tax, 'gross_total' => $totals->gross, 'posted_at' => now(), 'locked_at' => now()]);

        $bill->refresh();
        $this->assertEquals(107.0, (float) $bill->gross_total);
        $this->assertNotNull($bill->locked_at);

        $expense = Expense::create([
            'company_id' => $company->id,
            'merchant' => 'Store',
            'date' => now()->toDateString(),
            'category' => 'Supplies',
            'status' => 'approved',
        ]);

        ExpenseLine::create([
            'company_id' => $company->id,
            'expense_id' => $expense->id,
            'description' => 'Receipt',
            'quantity' => 1,
            'unit_price' => 50,
            'net_amount' => 50,
            'tax_amount' => 3.5,
            'gross_amount' => 53.5,
        ]);

        $expenseTotals = $expense->lines()->selectRaw('SUM(net_amount) net, SUM(tax_amount) tax, SUM(gross_amount) gross')->first();
        $expense->update(['status' => 'posted', 'net_total' => $expenseTotals->net, 'tax_total' => $expenseTotals->tax, 'gross_total' => $expenseTotals->gross, 'posted_at' => now(), 'locked_at' => now()]);

        $expense->refresh();
        $this->assertEquals(53.5, (float) $expense->gross_total);
        $this->assertNotNull($expense->locked_at);

        $this->assertTrue($user->companies()->where('companies.id', $company->id)->exists());
    }
}
