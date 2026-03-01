<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\WarehouseStocksRelationManager;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ProductPricingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Product details')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('General')
                        ->schema([
                            Forms\Components\TextInput::make('name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('sku')->maxLength(255),
                            Forms\Components\TextInput::make('ean')
                                ->label('EAN')
                                ->maxLength(64)
                                ->unique(ignoreRecord: true),
                            Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options(ProductCategory::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('supplier_id')
                                ->label('Supplier')
                                ->options(Supplier::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload(),
                            Forms\Components\TextInput::make('lead_time_days')->numeric()->minValue(0),
                            Forms\Components\Textarea::make('description')->columnSpanFull(),
                            Forms\Components\Select::make('product_type')->options(['stock' => 'Stock', 'service' => 'Service'])->required(),
                            Forms\Components\Toggle::make('is_active')->default(true),
                        ])->columns(2),

                    Forms\Components\Tabs\Tab::make('Pricing')
                        ->schema([
                            Forms\Components\TextInput::make('cost')->numeric()->minValue(0)->step('0.0001')->default(0),
                            Forms\Components\TextInput::make('default_margin_percent')->numeric()->step('0.001')->default(0),
                            Forms\Components\Placeholder::make('suggested_sale_price')
                                ->label('Suggested sale price')
                                ->content(fn (Get $get): string => number_format(
                                    round((float) ($get('cost') ?? 0) * (1 + ((float) ($get('default_margin_percent') ?? 0) / 100)), 4),
                                    4
                                )),
                            Forms\Components\Repeater::make('companyPricings')
                                ->relationship('companyPricings')
                                ->label('Company-specific margin overrides')
                                ->schema([
                                    Forms\Components\Select::make('company_id')
                                        ->label('Company')
                                        ->options(Company::query()->orderBy('name')->pluck('name', 'id'))
                                        ->required()
                                        ->searchable(),
                                    Forms\Components\TextInput::make('margin_percent')->numeric()->step('0.001'),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ])->columns(3),

                    Forms\Components\Tabs\Tab::make('Stock')
                        ->schema([
                            Forms\Components\Select::make('default_warehouse_id')
                                ->label('Default warehouse')
                                ->options(Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload(),
                            Forms\Components\Repeater::make('warehouseStocks')
                                ->relationship('warehouseStocks')
                                ->label('Stock by warehouse')
                                ->schema([
                                    Forms\Components\Select::make('warehouse_id')
                                        ->label('Warehouse')
                                        ->options(Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                                        ->required()
                                        ->searchable(),
                                    Forms\Components\TextInput::make('quantity')->numeric()->required()->default(0),
                                    Forms\Components\TextInput::make('external_quantity')->numeric(),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                        ])->columns(1),

                    Forms\Components\Tabs\Tab::make('Media')
                        ->schema([
                            Forms\Components\TextInput::make('image_url')->label('Image URL')->url()->maxLength(2048),
                            Forms\Components\TextInput::make('image_path')->disabled()->dehydrated(false),
                            Forms\Components\Placeholder::make('image_preview')
                                ->label('Thumbnail')
                                ->content(function (Get $get): HtmlString|string {
                                    $path = $get('image_path');
                                    if (! $path) {
                                        return 'No image downloaded yet.';
                                    }

                                    return new HtmlString("<img src='/storage/{$path}' alt='Product image' style='max-height: 120px; border-radius: 8px;' />");
                                }),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sku')->searchable(),
            Tables\Columns\TextColumn::make('ean')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('category.name')->label('Category'),
            Tables\Columns\TextColumn::make('supplier.name')->label('Supplier'),
            Tables\Columns\TextColumn::make('product_type'),
            Tables\Columns\TextColumn::make('cost')->money('EUR'),
            Tables\Columns\TextColumn::make('default_margin_percent')->label('Default margin %')->numeric(decimalPlaces: 3),
            Tables\Columns\TextColumn::make('suggested_sale_price')
                ->label('Suggested sale')
                ->state(function (Product $record): string {
                    $company = Company::query()->first();
                    if (! $company) {
                        return number_format((float) ($record->cost ?? 0), 4);
                    }

                    return number_format(app(ProductPricingService::class)->calculateSalePrice($record, $company), 4);
                }),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            WarehouseStocksRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
