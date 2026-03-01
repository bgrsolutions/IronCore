<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\StockMoveResource\Pages;
use App\Models\StockMove;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMoveResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = StockMove::class;
    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Ledger';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 90;


    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('occurred_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('product.name')->searchable(),
            Tables\Columns\TextColumn::make('move_type'),
            Tables\Columns\TextColumn::make('qty'),
            Tables\Columns\TextColumn::make('unit_cost'),
            Tables\Columns\TextColumn::make('total_cost'),
        ])->filters([
            Tables\Filters\SelectFilter::make('move_type')->options([
                'receipt' => 'Receipt', 'sale' => 'Sale', 'adjustment_in' => 'Adjustment In', 'adjustment_out' => 'Adjustment Out',
                'transfer_in' => 'Transfer In', 'transfer_out' => 'Transfer Out', 'return_in' => 'Return In', 'return_out' => 'Return Out',
            ]),
        ])->actions([])->bulkActions([]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMoves::route('/')];
    }
}
