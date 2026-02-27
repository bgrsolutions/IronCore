<?php

namespace App\Filament\Resources\RepairResource\Pages;

use App\Filament\Resources\RepairResource;
use App\Support\Company\CompanyContext;
use Filament\Resources\Pages\CreateRecord;

class CreateRepair extends CreateRecord
{
    protected static string $resource = RepairResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = CompanyContext::get();

        return $data;
    }
}
