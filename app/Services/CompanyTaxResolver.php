<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SalesDocument;
use RuntimeException;

class CompanyTaxResolver
{
    public function resolveSalesTaxRate(SalesDocument $document, Company $company): float
    {
        return $this->resolveSalesTaxRateFromMode(
            $document->tax_mode,
            $document->tax_rate !== null ? (float) $document->tax_rate : null,
            $company
        );
    }

    public function resolveSalesTaxRateFromMode(?string $taxMode, ?float $taxRate, Company $company): float
    {
        $mode = $taxMode ?: 'inherit_company';

        return match ($mode) {
            'tax_exempt' => 0.0,
            'custom' => $this->resolveCustomRate($taxRate),
            default => $company->sales_tax_enabled ? (float) ($company->sales_tax_rate ?? 0) : 0.0,
        };
    }

    private function resolveCustomRate(?float $taxRate): float
    {
        if ($taxRate === null) {
            throw new RuntimeException('Custom tax mode requires a tax rate.');
        }

        return (float) $taxRate;
    }
}
