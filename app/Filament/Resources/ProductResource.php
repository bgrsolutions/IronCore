<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sku'),
            Forms\Components\TextInput::make('barcode'),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Textarea::make('description'),
            Forms\Components\Select::make('product_type')->options(['stock' => 'Stock', 'service' => 'Service'])->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sku')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('product_type'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProducts::route('/'), 'create' => Pages\CreateProduct::route('/create'), 'edit' => Pages\EditProduct::route('/{record}/edit')];
    }
}
