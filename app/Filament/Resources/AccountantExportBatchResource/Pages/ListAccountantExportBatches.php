<?php

namespace App\Filament\Resources\AccountantExportBatchResource\Pages;

use App\Filament\Resources\AccountantExportBatchResource;
use App\Services\AccountantExportService;
use App\Support\Company\CompanyContext;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountantExportBatches extends ListRecords
{
    protected static string $resource = AccountantExportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate Export')
                ->form([
                    \Filament\Forms\Components\Select::make('period_preset')
                        ->options(['month' => 'Month', 'quarter' => 'Quarter', 'custom' => 'Custom'])
                        ->default('month')
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('from_date'),
                    \Filament\Forms\Components\DatePicker::make('to_date'),
                    \Filament\Forms\Components\Toggle::make('breakdown_by_store')->default(false),
                ])
                ->action(function (array $data): void {
                    $now = now();
                    $preset = (string) ($data['period_preset'] ?? 'month');
                    $from = $preset === 'quarter' ? $now->copy()->startOfQuarter()->toDateString() : ($preset === 'month' ? $now->copy()->startOfMonth()->toDateString() : (string) $data['from_date']);
                    $to = $preset === 'quarter' ? $now->copy()->endOfQuarter()->toDateString() : ($preset === 'month' ? $now->copy()->endOfMonth()->toDateString() : (string) $data['to_date']);

                    app(AccountantExportService::class)->generateBatch(
                        (int) CompanyContext::get(),
                        $from,
                        $to,
                        (bool) ($data['breakdown_by_store'] ?? false),
                        auth()->id(),
                    );
                }),
        ];
    }
}
