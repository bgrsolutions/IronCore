<?php

namespace App\Filament\Resources\PurchasePlanResource\Pages;

use App\Filament\Resources\PurchasePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchasePlans extends ListRecords
{
    protected static string $resource = PurchasePlanResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
