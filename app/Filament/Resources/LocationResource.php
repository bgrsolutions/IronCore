<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use App\Models\Warehouse;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Location::class;
    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 80;


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\Select::make('warehouse_id')->options(fn () => Warehouse::query()->pluck('name', 'id'))->required(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code')->required(),
            Forms\Components\Toggle::make('is_default')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('warehouse.name'),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\IconColumn::make('is_default')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLocations::route('/'), 'create' => Pages\CreateLocation::route('/create'), 'edit' => Pages\EditLocation::route('/{record}/edit')];
    }
}
