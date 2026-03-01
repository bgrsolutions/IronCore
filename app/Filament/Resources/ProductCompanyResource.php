<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\ProductCompanyResource\Pages;
use App\Models\Product;
use App\Models\ProductCompany;
use App\Models\ProductReorderSetting;
use App\Models\Supplier;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductCompanyResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = ProductCompany::class;

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Settings';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\Select::make('product_id')->options(fn () => Product::query()->pluck('name', 'id'))->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\TextInput::make('default_igic_rate')->numeric(),
            Forms\Components\TextInput::make('sale_price')->numeric(),
            Forms\Components\TextInput::make('reorder_min_qty')->numeric(),
            Forms\Components\Select::make('preferred_supplier_id')->options(fn () => Supplier::query()->pluck('name', 'id')),

            Forms\Components\Section::make('Reorder Settings')
                ->schema([
                    Forms\Components\Toggle::make('reorder_is_enabled')->default(true),
                    Forms\Components\TextInput::make('reorder_lead_time_days')->numeric()->default(3),
                    Forms\Components\TextInput::make('reorder_safety_days')->numeric()->default(7),
                    Forms\Components\TextInput::make('reorder_min_days_cover')->numeric()->default(14),
                    Forms\Components\TextInput::make('reorder_max_days_cover')->numeric()->default(30),
                    Forms\Components\TextInput::make('reorder_min_order_qty')->numeric(),
                    Forms\Components\TextInput::make('reorder_pack_size_qty')->numeric(),
                    Forms\Components\Select::make('reorder_preferred_supplier_id')->options(fn () => Supplier::query()->pluck('name', 'id')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('product.name')->searchable(),
            Tables\Columns\TextColumn::make('sale_price'),
            Tables\Columns\TextColumn::make('reorder_min_qty'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\IconColumn::make('reorder_enabled')
                ->label('Reorder Enabled')
                ->state(function (ProductCompany $record): bool {
                    return (bool) ProductReorderSetting::query()
                        ->where('company_id', $record->company_id)
                        ->where('product_id', $record->product_id)
                        ->value('is_enabled');
                })
                ->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProductCompanies::route('/'), 'create' => Pages\CreateProductCompany::route('/create'), 'edit' => Pages\EditProductCompany::route('/{record}/edit')];
    }
}
