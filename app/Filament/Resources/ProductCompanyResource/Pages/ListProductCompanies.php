<?php

namespace App\Filament\Resources\ProductCompanyResource\Pages;

use App\Filament\Resources\ProductCompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductCompanies extends ListRecords
{
    protected static string $resource = ProductCompanyResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
