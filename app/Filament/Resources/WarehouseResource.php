<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 70;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code')->required(),
            Forms\Components\Select::make('type')
                ->options([
                    'store' => 'Store',
                    'virtual' => 'Virtual',
                    'supplier' => 'Supplier',
                    'online' => 'Online',
                ]),
            Forms\Components\Toggle::make('is_default')->default(false),
            Forms\Components\Toggle::make('counts_for_stock')->default(true),
            Forms\Components\Toggle::make('is_external_supplier_stock')->default(false),
            Forms\Components\TextInput::make('address_street'),
            Forms\Components\TextInput::make('address_city'),
            Forms\Components\TextInput::make('address_region'),
            Forms\Components\TextInput::make('address_postcode'),
            Forms\Components\TextInput::make('address_country')->maxLength(2),
            Forms\Components\TextInput::make('contact_name'),
            Forms\Components\TextInput::make('contact_email')->email(),
            Forms\Components\TextInput::make('contact_phone'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\TextColumn::make('type'),
            Tables\Columns\IconColumn::make('counts_for_stock')->boolean(),
            Tables\Columns\IconColumn::make('is_external_supplier_stock')->boolean(),
            Tables\Columns\IconColumn::make('is_default')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWarehouses::route('/'), 'create' => Pages\CreateWarehouse::route('/create'), 'edit' => Pages\EditWarehouse::route('/{record}/edit')];
    }
}
