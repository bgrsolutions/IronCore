<?php

namespace Tests\Feature;

use App\Domain\Repairs\RepairPublicFlowService;
use App\Domain\Repairs\RepairWorkflowService;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PublicToken;
use App\Models\Repair;
use App\Models\SalesDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RepairPublicFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setupRepair(bool $withPostedSalesDocument = false): Repair
    {
        $this->seed();
        $company = Company::first();
        $customer = Customer::create(['name' => 'Tablet Customer', 'email' => 'tablet@example.com']);

        $repair = Repair::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => 'ready',
            'device_brand' => 'Apple',
            'device_model' => 'iPhone 13',
            'reported_issue' => 'Screen replacement',
        ]);

        if ($withPostedSalesDocument) {
            $doc = SalesDocument::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'doc_type' => 'invoice',
                'series' => 'F',
                'number' => 1,
                'full_number' => 'F-2026-000001',
                'status' => 'posted',
                'issue_date' => now(),
                'posted_at' => now(),
                'locked_at' => now(),
                'net_total' => 100,
                'tax_total' => 7,
                'gross_total' => 107,
                'source' => 'manual',
            ]);
            $doc->lines()->create([
                'line_no' => 1,
                'description' => 'Screen replacement service',
                'qty' => 1,
                'unit_price' => 100,
                'tax_rate' => 7,
                'line_net' => 100,
                'line_tax' => 7,
                'line_gross' => 107,
            ]);
            $repair->update(['linked_sales_document_id' => $doc->id]);
        }

        return $repair->fresh();
    }

    public function test_expired_and_used_tokens_are_rejected(): void
    {
        $repair = $this->setupRepair();

        $expired = PublicToken::create([
            'company_id' => $repair->company_id,
            'repair_id' => $repair->id,
            'purpose' => 'repair_intake_signature',
            'token' => 'expired-token',
            'expires_at' => now()->subMinute(),
            'created_at' => now(),
        ]);

        $this->get('/p/repairs/'.$expired->token)->assertStatus(410);

        $token = app(RepairPublicFlowService::class)->generateToken($repair, 'repair_intake_signature', 30);
        $png = 'data:image/png;base64,'.base64_encode(self::tinyPng());
        $this->post('/p/repairs/'.$token->token.'/sign', ['signature' => $png])->assertOk();
        $this->post('/p/repairs/'.$token->token.'/sign', ['signature' => $png])->assertStatus(410);
    }

    public function test_signature_storage_path_and_hash_are_saved(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $repair = $this->setupRepair();
        $token = app(RepairPublicFlowService::class)->generateToken($repair, 'repair_intake_signature');
        $pngBytes = self::tinyPng();

        $this->post('/p/repairs/'.$token->token.'/sign', [
            'signer_name' => 'Jane Customer',
            'signature' => 'data:image/png;base64,'.base64_encode($pngBytes),
        ])->assertOk();

        $sig = DB::table('repair_signatures')->where('repair_id', $repair->id)->first();
        $this->assertNotNull($sig);
        $this->assertMatchesRegularExpression('/^'.$repair->company_id.'\/repairs\/'.$repair->id.'\/intake\/[0-9]+\.png$/', $sig->signature_image_path);
        $this->assertSame(hash('sha256', $pngBytes), $sig->signature_hash);
        Storage::disk('local')->assertExists($sig->signature_image_path);
    }

    public function test_token_purpose_mismatch_is_rejected(): void
    {
        $repair = $this->setupRepair();
        $feedbackToken = app(RepairPublicFlowService::class)->generateToken($repair, 'repair_feedback');

        $this->post('/p/repairs/'.$feedbackToken->token.'/sign', [
            'signature' => 'data:image/png;base64,'.base64_encode(self::tinyPng()),
        ])->assertStatus(409);

        $intakeToken = app(RepairPublicFlowService::class)->generateToken($repair, 'repair_intake_signature');
        $this->post('/p/repairs/'.$intakeToken->token.'/sign', [
            'signature' => 'data:image/png;base64,'.base64_encode(self::tinyPng()),
        ])->assertOk();

        $this->assertDatabaseMissing('repair_pickups', ['repair_id' => $repair->id]);

        $mismatchFeedbackToken = app(RepairPublicFlowService::class)->generateToken($repair, 'repair_intake_signature');
        $this->post('/p/repairs/'.$mismatchFeedbackToken->token.'/feedback', [
            'rating' => 4,
        ])->assertStatus(409);

        $this->post('/p/repairs/'.$intakeToken->token.'/feedback', [
            'rating' => 4,
        ])->assertStatus(410);
    }

    public function test_pickup_requires_posted_sales_document_when_configured(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local', 'repairs.require_invoice_before_pickup' => true]);

        $repair = $this->setupRepair(false);
        $token = app(RepairPublicFlowService::class)->generateToken($repair, 'repair_pickup_signature');

        $response = $this->post('/p/repairs/'.$token->token.'/sign', [
            'signature' => 'data:image/png;base64,'.base64_encode(self::tinyPng()),
        ]);

        $response->assertStatus(409);
        $response->assertSee('Invoice must be posted before pickup.');
        $this->assertDatabaseMissing('repair_pickups', ['repair_id' => $repair->id]);
    }

    public function test_pickup_signature_creates_pickup_and_feedback_token_and_receipt_document(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local', 'repairs.require_invoice_before_pickup' => true]);

        $repair = $this->setupRepair(true);
        $token = app(RepairPublicFlowService::class)->generateToken($repair, 'repair_pickup_signature');

        $this->post('/p/repairs/'.$token->token.'/sign', [
            'signature' => 'data:image/png;base64,'.base64_encode(self::tinyPng()),
        ])->assertOk();

        $this->assertDatabaseHas('repair_pickups', ['repair_id' => $repair->id, 'pickup_confirmed' => true]);
        $this->assertDatabaseHas('public_tokens', ['repair_id' => $repair->id, 'purpose' => 'repair_feedback']);
        $this->assertEquals('collected', $repair->fresh()->status);

        $service = app(RepairPublicFlowService::class);
        $doc = $service->generatePickupReceipt($repair->fresh());

        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'company_id' => $repair->company_id]);
        $this->assertDatabaseHas('document_attachments', ['document_id' => $doc->id, 'attachable_type' => Repair::class, 'attachable_id' => $repair->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair.pickup_receipt_generated', 'auditable_id' => $repair->id]);
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_status_rules_enforced_and_history_logged(): void
    {
        $repair = $this->setupRepair(false);
        $service = app(RepairWorkflowService::class);

        $this->expectException(RuntimeException::class);
        $service->transitionModel($repair, 'collected', 1, 'invalid collect');
    }


    public function test_status_invoiced_rejected_without_linked_posted_sales_document(): void
    {
        $repair = $this->setupRepair(false);
        $repair->update(['status' => 'collected']);

        $this->expectException(RuntimeException::class);
        app(RepairWorkflowService::class)->transitionModel($repair->fresh(), 'invoiced', 1, 'missing sales doc');
    }

    public function test_status_invoiced_requires_linked_posted_sales_document_and_logs_history(): void
    {
        $repair = $this->setupRepair(true);
        $repair->update(['status' => 'collected']);

        $service = app(RepairWorkflowService::class);
        $service->transitionModel($repair->fresh(), 'invoiced', 1, 'sales doc posted');

        $this->assertDatabaseHas('repair_status_history', [
            'repair_id' => $repair->id,
            'from_status' => 'collected',
            'to_status' => 'invoiced',
        ]);
    }

    private static function tinyPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9s6QzK0AAAAASUVORK5CYII=');
    }
}
