<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\SalesDocument;
use App\Services\CompanyTaxResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTaxResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_inherit_company_tax_respects_company_settings(): void
    {
        $resolver = app(CompanyTaxResolver::class);
        $company = Company::query()->create([
            'name' => 'ACME',
            'sales_tax_enabled' => true,
            'sales_tax_rate' => 7,
        ]);

        $document = SalesDocument::query()->create([
            'company_id' => $company->id,
            'doc_type' => 'ticket',
            'series' => 'T',
            'status' => 'draft',
            'issue_date' => now(),
            'tax_mode' => 'inherit_company',
        ]);

        $this->assertSame(7.0, $resolver->resolveSalesTaxRate($document, $company));

        $company->update(['sales_tax_enabled' => false]);
        $this->assertSame(0.0, $resolver->resolveSalesTaxRate($document, $company->fresh()));
    }

    public function test_custom_and_exempt_modes(): void
    {
        $resolver = app(CompanyTaxResolver::class);
        $company = Company::query()->create(['name' => 'ACME']);

        $document = SalesDocument::query()->create([
            'company_id' => $company->id,
            'doc_type' => 'invoice',
            'series' => 'I',
            'status' => 'draft',
            'issue_date' => now(),
            'tax_mode' => 'custom',
            'tax_rate' => 9.5,
        ]);

        $this->assertSame(9.5, $resolver->resolveSalesTaxRate($document, $company));

        $document->update(['tax_mode' => 'tax_exempt']);
        $this->assertSame(0.0, $resolver->resolveSalesTaxRate($document->fresh(), $company));
    }
}
