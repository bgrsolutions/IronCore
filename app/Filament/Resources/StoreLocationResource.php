<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\StoreLocationResource\Pages;
use App\Models\StoreLocation;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreLocationResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = StoreLocation::class;

    protected static ?string $navigationGroup = 'Administration';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->isManagerOrAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code')->maxLength(20),
            Forms\Components\Textarea::make('address')->rows(2),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreLocations::route('/'),
            'create' => Pages\CreateStoreLocation::route('/create'),
            'edit' => Pages\EditStoreLocation::route('/{record}/edit'),
        ];
    }
}
