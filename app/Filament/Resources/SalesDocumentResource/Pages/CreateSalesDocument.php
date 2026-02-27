<?php

namespace App\Filament\Resources\SalesDocumentResource\Pages;

use App\Filament\Resources\SalesDocumentResource;
use App\Support\Company\CompanyContext;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesDocument extends CreateRecord
{
    protected static string $resource = SalesDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = CompanyContext::get();
        $data['status'] = 'draft';
        return $data;
    }
}
