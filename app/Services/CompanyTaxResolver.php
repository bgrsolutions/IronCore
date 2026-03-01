<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SalesDocument;
use RuntimeException;

class CompanyTaxResolver
{
    public function resolveSalesTaxRate(SalesDocument $document, Company $company): float
    {
        $mode = $document->tax_mode ?: 'inherit_company';

        return match ($mode) {
            'tax_exempt' => 0.0,
            'custom' => $this->resolveCustomRate($document),
            default => $company->sales_tax_enabled ? (float) ($company->sales_tax_rate ?? 0) : 0.0,
        };
    }

    private function resolveCustomRate(SalesDocument $document): float
    {
        if ($document->tax_rate === null) {
            throw new RuntimeException('Custom tax mode requires a tax rate.');
        }

        return (float) $document->tax_rate;
    }
}
