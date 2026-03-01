<?php

namespace App\Filament\Resources\SalesDocumentResource\Pages;

use App\Filament\Resources\SalesDocumentResource;
use App\Models\Company;
use App\Models\Product;
use App\Services\SalesPricingService;
use App\Support\Company\CompanyContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSalesDocument extends CreateRecord
{
    protected static string $resource = SalesDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = CompanyContext::get();
        $data['status'] = 'draft';
        if (auth()->check() && ! auth()->user()->isManagerOrAdmin() && empty($data['store_location_id'])) {
            throw ValidationException::withMessages(['store_location_id' => 'Store location is required for staff users.']);
        }

        $company = Company::query()->find((int) $data['company_id']);
        if ($company) {
            foreach ((array) ($data['lines'] ?? []) as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $product = Product::query()->find($productId);
                if (! $product) {
                    continue;
                }

                $landed = app(SalesPricingService::class)->calculateLandedCost($product, $company);
                $unit = (float) ($line['unit_price'] ?? 0);
                if ($unit + 0.00001 < $landed) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.unit_price" => 'Selling net price cannot be below landed cost.',
                    ]);
                }
            }
        }

        return $data;
    }
}
