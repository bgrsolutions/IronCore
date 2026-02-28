<?php

namespace App\Filament\Resources\RepairResource\Pages;

use App\Filament\Resources\RepairResource;
use App\Support\Company\CompanyContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateRepair extends CreateRecord
{
    protected static string $resource = RepairResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = CompanyContext::get();
        if (auth()->check() && ! auth()->user()->isManagerOrAdmin() && empty($data['store_location_id'])) {
            throw ValidationException::withMessages(['store_location_id' => 'Store location is required for staff users.']);
        }

        return $data;
    }
}
