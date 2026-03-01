<?php
namespace App\Filament\Resources\SubscriptionRunsResource\Pages;

use App\Filament\Resources\SubscriptionRunsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionRuns extends ListRecords
{
    protected static string $resource = SubscriptionRunsResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
