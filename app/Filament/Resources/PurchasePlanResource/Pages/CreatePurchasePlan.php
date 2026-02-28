<?php

namespace App\Filament\Resources\PurchasePlanResource\Pages;

use App\Filament\Resources\PurchasePlanResource;
use App\Support\Company\CompanyContext;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchasePlan extends CreateRecord
{
    protected static string $resource = PurchasePlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = CompanyContext::get();

        return $data;
    }
}
