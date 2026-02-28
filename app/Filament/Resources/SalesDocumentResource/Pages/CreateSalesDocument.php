<?php

namespace App\Filament\Resources\SalesDocumentResource\Pages;

use App\Filament\Resources\SalesDocumentResource;
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
        return $data;
    }
}
