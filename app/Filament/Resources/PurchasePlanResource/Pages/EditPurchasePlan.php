<?php

namespace App\Filament\Resources\PurchasePlanResource\Pages;

use App\Filament\Resources\PurchasePlanResource;
use App\Services\PurchasePlanService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchasePlan extends EditRecord
{
    protected static string $resource = PurchasePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mark_ordered')
                ->label('Mark Ordered')
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'ordered'], true))
                ->action(function (): void {
                    app(PurchasePlanService::class)->markOrdered($this->record);
                    $this->record->refresh();
                }),
            Actions\Action::make('receive_item')
                ->label('Receive Items')
                ->form([
                    \Filament\Forms\Components\Select::make('item_id')->options(fn () => $this->record->items()->with('product')->get()->pluck('product.name', 'id'))->required(),
                    \Filament\Forms\Components\TextInput::make('qty')->numeric()->required(),
                ])
                ->visible(fn (): bool => in_array($this->record->status, ['ordered', 'partially_received'], true))
                ->action(function (array $data): void {
                    app(PurchasePlanService::class)->receiveItem($this->record, (int) $data['item_id'], (float) $data['qty']);
                    $this->record->refresh();
                }),
        ];
    }
}
