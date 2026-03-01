<?php

namespace App\Filament\Resources\SalesDocumentResource\Pages;

use App\Filament\Resources\SalesDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesDocuments extends ListRecords
{
    protected static string $resource = SalesDocumentResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
