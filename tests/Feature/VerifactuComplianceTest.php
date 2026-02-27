<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SalesDocument;
use App\Models\VerifactuEvent;
use App\Models\VerifactuExport;
use App\Services\VeriFactuService;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifactuComplianceTest extends TestCase
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

    private function makeDraft(int $companyId, string $series = 'F'): SalesDocument
    {
        $doc = SalesDocument::create([
            'company_id' => $companyId,
            'doc_type' => 'invoice',
            'series' => $series,
            'status' => 'draft',
            'issue_date' => now(),
            'source' => 'manual',
            'currency' => 'EUR',
        ]);

        $doc->lines()->create([
            'line_no' => 1,
            'description' => 'Line',
            'qty' => 1,
            'unit_price' => 100,
            'tax_rate' => 7,
            'line_net' => 100,
            'line_tax' => 7,
            'line_gross' => 107,
        ]);

        return $doc;
    }

    public function test_hash_generation_is_deterministic_for_posted_document(): void
    {
        ['company' => $company] = $this->setupContext();
        $doc = $this->makeDraft($company->id);

        $posted = app(\App\Services\SalesDocumentService::class)->post($doc);

        $vf = app(VeriFactuService::class);
        $canonicalA = $vf->canonicalizePayload($posted, (array) $posted->immutable_payload, $posted->previous_hash, (string) $posted->full_number);
        $canonicalB = $vf->canonicalizePayload($posted, (array) $posted->immutable_payload, $posted->previous_hash, (string) $posted->full_number);

        $this->assertSame($vf->computeHash($canonicalA), $vf->computeHash($canonicalB));
        $this->assertSame($posted->hash, $vf->computeHash($canonicalA));
    }

    public function test_chaining_is_correct_within_company_and_series(): void
    {
        ['company' => $company] = $this->setupContext();

        $first = app(\App\Services\SalesDocumentService::class)->post($this->makeDraft($company->id, 'F'));
        $second = app(\App\Services\SalesDocumentService::class)->post($this->makeDraft($company->id, 'F'));

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->hash, $second->previous_hash);
    }

    public function test_chain_is_isolated_across_series_and_companies(): void
    {
        ['company' => $company, 'user' => $user] = $this->setupContext();

        $other = Company::create(['name' => 'Other Co', 'tax_id' => 'B00000002']);
        $user->companies()->attach($other->id);

        $firstF = app(\App\Services\SalesDocumentService::class)->post($this->makeDraft($company->id, 'F'));
        $firstT = app(\App\Services\SalesDocumentService::class)->post($this->makeDraft($company->id, 'T'));

        $this->withSession([CompanyContext::SESSION_KEY => $other->id]);
        $otherF = app(\App\Services\SalesDocumentService::class)->post($this->makeDraft($other->id, 'F'));

        $this->assertNull($firstF->previous_hash);
        $this->assertNull($firstT->previous_hash);
        $this->assertNull($otherF->previous_hash);
    }

    public function test_qr_payload_contains_required_fields(): void
    {
        ['company' => $company] = $this->setupContext();
        $posted = app(\App\Services\SalesDocumentService::class)->post($this->makeDraft($company->id, 'F'));

        parse_str((string) $posted->qr_payload, $parsed);
        $this->assertArrayHasKey('nif', $parsed);
        $this->assertArrayHasKey('num', $parsed);
        $this->assertArrayHasKey('fecha', $parsed);
        $this->assertArrayHasKey('importe', $parsed);
        $this->assertArrayHasKey('hash', $parsed);
    }



    public function test_compute_hash_logs_event_and_rethrows_on_invalid_payload_encoding(): void
    {
        ['company' => $company] = $this->setupContext();
        $vf = app(VeriFactuService::class);

        $this->expectException(\JsonException::class);

        try {
            $vf->computeHash([
                'company_id' => $company->id,
                'broken' => INF,
            ]);
        } finally {
            $this->assertDatabaseHas('verifactu_events', [
                'company_id' => $company->id,
                'event_type' => 'verifactu.hash.encoding_error',
            ]);
        }
    }

    public function test_qr_payload_uses_rfc3986_deterministic_encoding(): void
    {
        ['company' => $company] = $this->setupContext();
        $doc = $this->makeDraft($company->id, 'F');
        $doc->series = 'F test';
        $doc->save();

        $posted = app(\App\Services\SalesDocumentService::class)->post($doc);

        $this->assertStringNotContainsString('+', (string) $posted->qr_payload);
        $this->assertStringContainsString('%20', (string) $posted->qr_payload);
    }

    public function test_export_file_is_created_and_registry_is_populated(): void
    {
        ['company' => $company] = $this->setupContext();
        Storage::fake('local');

        app(\App\Services\SalesDocumentService::class)->post($this->makeDraft($company->id, 'F'));

        $this->artisan('verifactu:export', [
            '--company' => $company->id,
            '--from' => now()->toDateString(),
            '--to' => now()->toDateString(),
        ])->assertSuccessful();

        $export = VerifactuExport::query()->latest('id')->first();
        $this->assertNotNull($export);
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertGreaterThan(0, $export->items()->count());
    }
}
