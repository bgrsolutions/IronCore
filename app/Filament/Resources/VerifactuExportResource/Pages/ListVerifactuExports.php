<?php

namespace App\Filament\Resources\VerifactuExportResource\Pages;

use App\Filament\Resources\VerifactuExportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVerifactuExports extends ListRecords
{
    protected static string $resource = VerifactuExportResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
