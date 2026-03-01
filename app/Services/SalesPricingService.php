<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use RuntimeException;

class SalesPricingService
{
    public function __construct(
        private readonly ProductPricingService $productPricingService,
        private readonly CompanyTaxResolver $companyTaxResolver
    ) {}

    public function calculateSellingNet(Product $product, Company $company): float
    {
        $marginPercent = $this->productPricingService->resolveMarginPercent($product, $company);
        $landedCost = $this->calculateLandedCost($product, $company);

        if ($landedCost <= 0) {
            return 0.0;
        }

        if ($marginPercent >= 100) {
            throw new RuntimeException('Margin percent must be below 100.');
        }

        $divisor = 1 - ($marginPercent / 100);
        if ($divisor <= 0) {
            throw new RuntimeException('Margin percent must be below 100.');
        }

        return round($landedCost / $divisor, 2);
    }

    /** @return array{line_net: float, line_tax: float, line_gross: float} */
    public function calculateLineTotals(float $unitPrice, float $quantity, float $taxRate): array
    {
        $lineNet = round($unitPrice * $quantity, 2);
        $lineTax = round($lineNet * ($taxRate / 100), 2);
        $lineGross = round($lineNet + $lineTax, 2);

        return [
            'line_net' => $lineNet,
            'line_tax' => $lineTax,
            'line_gross' => $lineGross,
        ];
    }

    public function enforceMinimumPrice(float $sellingNet, float $landedCost): void
    {
        if ($sellingNet + 0.00001 < $landedCost) {
            throw new RuntimeException('Selling net price cannot be below landed cost.');
        }
    }

    public function resolveSalesTaxRate(?string $taxMode, ?float $taxRate, Company $company): float
    {
        return $this->companyTaxResolver->resolveSalesTaxRateFromMode($taxMode, $taxRate, $company);
    }

    public function calculateLandedCost(Product $product, Company $company): float
    {
        $cost = (float) ($product->cost ?? 0);
        $purchaseTaxRate = (float) ($company->purchase_tax_rate ?? 0);

        return round($cost * (1 + ($purchaseTaxRate / 100)), 2);
    }

    /** @return array{unit_price: float,tax_rate: float,line_net: float,line_tax: float,line_gross: float,landed_cost: float} */
    public function calculateLineForProduct(
        Product $product,
        Company $company,
        float $quantity,
        ?string $taxMode,
        ?float $customTaxRate
    ): array {
        $unitPrice = $this->calculateSellingNet($product, $company);
        $taxRate = $this->resolveSalesTaxRate($taxMode, $customTaxRate, $company);
        $totals = $this->calculateLineTotals($unitPrice, $quantity, $taxRate);

        return [
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'line_net' => $totals['line_net'],
            'line_tax' => $totals['line_tax'],
            'line_gross' => $totals['line_gross'],
            'landed_cost' => $this->calculateLandedCost($product, $company),
        ];
    }
}
