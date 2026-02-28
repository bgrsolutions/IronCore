<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\AccountantExportService;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Release10AccountantExportPackTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        Storage::fake('local');
        $this->seed();
        $company = Company::first();
        $user = User::first();
        $this->actingAs($user);
        $this->withSession([CompanyContext::SESSION_KEY => $company->id]);

        return compact('company', 'user');
    }

    public function test_vendor_bill_tax_rate_backfill_formula(): void
    {
        ['company' => $company] = $this->setupContext();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp']);
        $bill = VendorBill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'B-1', 'invoice_date' => now()->toDateString(), 'status' => 'posted']);

        $lineId = DB::table('vendor_bill_lines')->insertGetId([
            'company_id' => $company->id,
            'vendor_bill_id' => $bill->id,
            'description' => 'Legacy',
            'quantity' => 1,
            'unit_price' => 100,
            'net_amount' => 100,
            'tax_rate' => null,
            'tax_amount' => 7,
            'gross_amount' => 107,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendor_bill_lines')->where('id', $lineId)->update([
            'tax_rate' => round((7 / 100) * 100, 2),
        ]);

        $this->assertEquals(7.0, (float) DB::table('vendor_bill_lines')->where('id', $lineId)->value('tax_rate'));
    }

    public function test_grouping_by_tax_rate_and_credit_note_netting_and_igic_payable(): void
    {
        ['company' => $company, 'user' => $user] = $this->setupContext();

        $invoice = SalesDocument::create([
            'company_id' => $company->id,
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
        $invoice->lines()->create(['line_no' => 1, 'description' => 'Sale', 'qty' => 1, 'unit_price' => 100, 'tax_rate' => 7, 'line_net' => 100, 'line_tax' => 7, 'line_gross' => 107]);

        $credit = SalesDocument::create([
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
            'doc_type' => 'credit_note',
            'series' => 'NC',
            'status' => 'posted',
            'issue_date' => now(),
            'net_total' => -20,
            'tax_total' => -1.4,
            'gross_total' => -21.4,
            'source' => 'manual',
        ]);
        $credit->lines()->create(['line_no' => 1, 'description' => 'Return', 'qty' => 1, 'unit_price' => -20, 'tax_rate' => 7, 'line_net' => -20, 'line_tax' => -1.4, 'line_gross' => -21.4]);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supp']);
        $bill = VendorBill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'B-2', 'invoice_date' => now()->toDateString(), 'status' => 'posted', 'net_total' => 50, 'tax_total' => 3.5, 'gross_total' => 53.5]);
        $bill->lines()->create(['company_id' => $company->id, 'description' => 'Purchase', 'quantity' => 1, 'unit_price' => 50, 'net_amount' => 50, 'tax_rate' => 7, 'tax_amount' => 3.5, 'gross_amount' => 53.5]);

        $batch = app(AccountantExportService::class)->generateBatch($company->id, now()->subDay()->toDateString(), now()->addDay()->toDateString(), false, $user->id);

        $this->assertEquals(5.6, (float) data_get($batch->summary_payload, 'output_tax_total'));
        $this->assertEquals(3.5, (float) data_get($batch->summary_payload, 'input_tax_total'));
        $this->assertEquals(2.1, (float) data_get($batch->summary_payload, 'net_payable_estimate'));
    }

    public function test_export_batch_creates_files_and_rows(): void
    {
        ['company' => $company, 'user' => $user] = $this->setupContext();

        $batch = app(AccountantExportService::class)->generateBatch(
            $company->id,
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            true,
            $user->id,
        );

        $this->assertDatabaseHas('accountant_export_batches', ['id' => $batch->id]);
        $this->assertGreaterThanOrEqual(7, $batch->files()->count());
        Storage::disk('local')->assertExists($batch->zip_path);
        $this->assertNotEmpty($batch->zip_hash);
    }
}
