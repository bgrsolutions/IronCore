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
            Forms\Components\Toggle::make('is_default')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\IconColumn::make('is_default')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWarehouses::route('/'), 'create' => Pages\CreateWarehouse::route('/create'), 'edit' => Pages\EditWarehouse::route('/{record}/edit')];
    }
}
