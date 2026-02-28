<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\PurchasePlanResource\Pages;
use App\Filament\Resources\PurchasePlanResource\RelationManagers\ItemsRelationManager;
use App\Models\PurchasePlan;
use App\Models\StoreLocation;
use App\Models\Supplier;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchasePlanResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = PurchasePlan::class;

    protected static ?string $navigationGroup = 'Purchasing';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\Select::make('supplier_id')->options(fn () => Supplier::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('store_location_id')->options(fn () => StoreLocation::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\DateTimePicker::make('planned_at')->default(now())->required(),
            Forms\Components\DatePicker::make('expected_at'),
            Forms\Components\Select::make('status')->options([
                'draft' => 'Draft',
                'ordered' => 'Ordered',
                'partially_received' => 'Partially Received',
                'received' => 'Received',
                'cancelled' => 'Cancelled',
            ])->required(),
            Forms\Components\Textarea::make('notes')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('Plan #'),
            Tables\Columns\TextColumn::make('supplier.name'),
            Tables\Columns\TextColumn::make('storeLocation.name')->label('Store'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('expected_at')->date(),
            Tables\Columns\TextColumn::make('planned_at')->dateTime(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchasePlans::route('/'),
            'create' => Pages\CreatePurchasePlan::route('/create'),
            'edit' => Pages\EditPurchasePlan::route('/{record}/edit'),
        ];
    }
}
