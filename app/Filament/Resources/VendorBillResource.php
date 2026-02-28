<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\VendorBillResource\Pages;
use App\Filament\Resources\VendorBillResource\RelationManagers\DocumentAttachmentsRelationManager;
use App\Models\PurchasePlan;
use App\Models\StoreLocation;
use App\Models\Supplier;
use App\Models\VendorBill;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorBillResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = VendorBill::class;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if (auth()->check() && ! auth()->user()->isManagerOrAdmin()) {
            $query->whereIn('store_location_id', auth()->user()->assignedStoreLocationIds() ?: [0]);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        $isManager = $user?->isManagerOrAdmin() ?? false;
        $storeIds = $user?->assignedStoreLocationIds() ?? [];

        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\Select::make('store_location_id')->label('Store')->options(fn () => StoreLocation::query()->when(! $isManager, fn ($q) => $q->whereIn('id', $storeIds ?: [0]))->pluck('name', 'id'))->required(! $isManager),
            Forms\Components\Select::make('supplier_id')->options(fn () => Supplier::query()->pluck('name', 'id'))->required(),
            Forms\Components\Select::make('purchase_plan_id')->options(fn () => PurchasePlan::query()->whereIn('status', ['draft', 'ordered', 'partially_received'])->pluck('id', 'id')),
            Forms\Components\TextInput::make('invoice_number')->required(),
            Forms\Components\DatePicker::make('invoice_date')->required(),
            Forms\Components\DatePicker::make('due_date'),
            Forms\Components\Repeater::make('lines')->relationship('lines')->schema([
                Forms\Components\Select::make('product_id')->relationship('product','name')->searchable(),
                Forms\Components\Toggle::make('is_stock_item')->default(false),
                Forms\Components\TextInput::make('description')->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->default(1),
                Forms\Components\TextInput::make('unit_price')->numeric()->default(0),
                Forms\Components\TextInput::make('net_amount')->numeric()->default(0),
                Forms\Components\TextInput::make('tax_amount')->numeric()->default(0),
                Forms\Components\TextInput::make('gross_amount')->numeric()->default(0),
                Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            ])->columns(3),
            Forms\Components\TextInput::make('status')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('invoice_number')->searchable(),
            Tables\Columns\TextColumn::make('storeLocation.name')->label('Store'),
            Tables\Columns\TextColumn::make('supplier.name'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('gross_total')->money('EUR'),
        ]);
    }

    public static function getRelations(): array
    {
        return [DocumentAttachmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVendorBills::route('/'), 'create' => Pages\CreateVendorBill::route('/create'), 'edit' => Pages\EditVendorBill::route('/{record}/edit')];
    }
}
