<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\AccountantExportBatchResource\Pages;
use App\Models\AccountantExportBatch;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountantExportBatchResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = AccountantExportBatch::class;

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?string $navigationLabel = 'Accountant Exports';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('from_date')->date(),
            Tables\Columns\TextColumn::make('to_date')->date(),
            Tables\Columns\TextColumn::make('zip_hash')->limit(24)->label('SHA256'),
            Tables\Columns\TextColumn::make('generated_at')->dateTime(),
        ])->actions([
            Tables\Actions\Action::make('download')
                ->url(fn (AccountantExportBatch $record) => route('accountant-exports.download', $record))
                ->openUrlInNewTab(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountantExportBatches::route('/'),
        ];
    }
}
