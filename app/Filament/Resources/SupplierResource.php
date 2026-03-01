<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Supplier::class;

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('tax_id'),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('phone'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([Tables\Columns\TextColumn::make('name')->searchable(), Tables\Columns\TextColumn::make('tax_id')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSuppliers::route('/'), 'create' => Pages\CreateSupplier::route('/create'), 'edit' => Pages\EditSupplier::route('/{record}/edit')];
    }
}
