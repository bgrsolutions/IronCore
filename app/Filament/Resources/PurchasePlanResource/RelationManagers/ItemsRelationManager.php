<?php

namespace App\Filament\Resources\PurchasePlanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')->relationship('product', 'name')->required()->searchable(),
            Forms\Components\TextInput::make('suggested_qty')->numeric()->required(),
            Forms\Components\TextInput::make('ordered_qty')->numeric(),
            Forms\Components\TextInput::make('received_qty')->numeric()->default(0),
            Forms\Components\TextInput::make('unit_cost_estimate')->numeric(),
            Forms\Components\Select::make('status')->options([
                'planned' => 'Planned',
                'ordered' => 'Ordered',
                'received' => 'Received',
                'cancelled' => 'Cancelled',
            ])->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('product.name'),
            Tables\Columns\TextColumn::make('suggested_qty'),
            Tables\Columns\TextColumn::make('ordered_qty'),
            Tables\Columns\TextColumn::make('received_qty'),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->headerActions([
            Tables\Actions\CreateAction::make(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }
}
