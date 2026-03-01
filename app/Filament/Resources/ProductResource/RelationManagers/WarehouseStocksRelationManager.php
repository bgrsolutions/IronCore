<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'warehouseStocks';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('warehouse_id')
                ->label('Warehouse')
                ->options(Warehouse::query()->pluck('name', 'id'))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('quantity')->numeric()->default(0)->required(),
            Forms\Components\TextInput::make('external_quantity')->numeric(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')->label('Warehouse')->searchable(),
                Tables\Columns\TextColumn::make('warehouse.type')->label('Type'),
                Tables\Columns\TextColumn::make('quantity')->numeric(decimalPlaces: 3),
                Tables\Columns\TextColumn::make('external_quantity')->numeric(decimalPlaces: 3),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
