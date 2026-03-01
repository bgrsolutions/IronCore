<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\VendorBillResource\Pages;
use App\Filament\Resources\VendorBillResource\RelationManagers\DocumentAttachmentsRelationManager;
use App\Models\Company;
use App\Models\Product;
use App\Models\StoreLocation;
use App\Models\Supplier;
use App\Models\VendorBill;
use App\Models\Warehouse;
use App\Services\VendorBillIntelligenceService;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorBillResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = VendorBill::class;

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Vendor Bills';

    protected static ?int $navigationSort = 40;

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
            Forms\Components\Section::make('Vendor Bill Header')
                ->description('Header fields are kept separate for cleaner scanning and data entry.')
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Company')
                        ->options(Company::query()->pluck('name', 'id'))
                        ->default(fn () => CompanyContext::get())
                        ->required(),
                    Forms\Components\Select::make('store_location_id')
                        ->label('Store')
                        ->options(fn () => StoreLocation::query()->when(! $isManager, fn ($q) => $q->whereIn('id', $storeIds ?: [0]))->pluck('name', 'id')),
                    Forms\Components\Select::make('supplier_id')->options(fn () => Supplier::query()->pluck('name', 'id'))->required(),
                    Forms\Components\TextInput::make('invoice_number')->required(),
                    Forms\Components\DatePicker::make('invoice_date')->required(),
                    Forms\Components\DatePicker::make('due_date'),
                    Forms\Components\Select::make('receiving_warehouse_id')
                        ->label('Receiving warehouse')
                        ->options(fn () => Warehouse::query()->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\FileUpload::make('pdf_path')
                        ->label('Vendor Bill PDF')
                        ->disk('local')
                        ->directory('vendor-bills')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240),
                ])->columns(2),

            Forms\Components\Section::make('Lines')
                ->description('Table-style line entry. EAN is first for scan-first workflows.')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->addActionLabel('Add line')
                        ->reorderable(false)
                        ->schema([
                            Forms\Components\TextInput::make('ean')
                                ->label('EAN')
                                ->maxLength(64)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                                    if (! $state) {
                                        return;
                                    }

                                    $product = Product::query()->where('ean', $state)->first();

                                    if (! $product) {
                                        $product = Product::query()->create([
                                            'name' => $get('description') ?: 'Vendor bill item',
                                            'ean' => $state,
                                            'cost' => (float) ($get('unit_price') ?? 0),
                                            'supplier_id' => (int) ($get('../../supplier_id') ?: 0) ?: null,
                                            'default_margin_percent' => 30,
                                            'category_id' => null,
                                            'product_type' => 'stock',
                                            'is_active' => true,
                                        ]);
                                    }

                                    $set('product_id', $product->id);
                                    $set('description', $get('description') ?: $product->name);
                                }),
                            Forms\Components\Select::make('product_id')->relationship('product', 'name')->searchable(),
                            Forms\Components\TextInput::make('description')->required()->columnSpan(2),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->step(1)
                                ->minValue(0)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => static::recalculateLine($get, $set)),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Cost (purchase)')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => static::recalculateLine($get, $set)),
                            Forms\Components\TextInput::make('net_amount')->label('Net')->numeric()->disabled()->dehydrated(),
                            Forms\Components\TextInput::make('tax_rate')
                                ->label('Tax %')
                                ->numeric()
                                ->default(fn (callable $get) => (float) Company::query()->whereKey((int) ($get('../../company_id') ?? CompanyContext::get()))->value('purchase_tax_rate'))
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => static::recalculateLine($get, $set)),
                            Forms\Components\TextInput::make('tax_amount')->label('Tax')->numeric()->disabled()->dehydrated(),
                            Forms\Components\TextInput::make('gross_amount')->label('Total')->numeric()->disabled()->dehydrated(),
                            Forms\Components\TextInput::make('margin_percent')
                                ->label('Profit margin % (based on landed cost)')
                                ->numeric()
                                ->maxValue(99.999)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => static::recalculateLine($get, $set)),
                            Forms\Components\TextInput::make('landed_cost_preview')
                                ->label('Landed cost')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn (callable $get): string => number_format(((float) ($get('unit_price') ?? 0)) * (1 + ((float) ($get('tax_rate') ?? 0) / 100)), 2)),
                            Forms\Components\TextInput::make('suggested_net_sale_price')->label('Suggested Selling (net)')->numeric()->disabled()->dehydrated(),
                            Forms\Components\Toggle::make('is_stock_item')->default(true),
                            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
                        ])->columns(10)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Totals')
                ->schema([
                    Forms\Components\Placeholder::make('vendor_subtotal')
                        ->label('Subtotal (Net)')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'net_amount'), 2)),
                    Forms\Components\Placeholder::make('vendor_tax_total')
                        ->label('Tax')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'tax_amount'), 2)),
                    Forms\Components\Placeholder::make('vendor_grand_total')
                        ->label('Total')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'gross_amount'), 2)),
                ])->columns(3),

            Forms\Components\TextInput::make('status')->disabled(),
        ]);
    }

    private static function recalculateLine(callable $get, callable $set): void
    {
        $qty = (float) ($get('quantity') ?? 0);
        $unitCost = (float) ($get('unit_price') ?? 0);
        $taxPercent = (float) ($get('tax_rate') ?? 0);
        $marginPercent = $get('margin_percent') !== null ? (float) $get('margin_percent') : 0.0;

        $net = round($qty * $unitCost, 2);
        $tax = round($net * ($taxPercent / 100), 2);
        $gross = round($net + $tax, 2);

        $set('net_amount', $net);
        $set('tax_amount', $tax);
        $set('gross_amount', $gross);

        if ($marginPercent >= 100 || $unitCost <= 0) {
            $set('suggested_net_sale_price', 0);

            return;
        }

        $suggestedSale = app(VendorBillIntelligenceService::class)
            ->calculateSuggestedNetSalePrice($unitCost, $taxPercent, $marginPercent);

        $set('suggested_net_sale_price', $suggestedSale);
    }

    /** @param array<int, array<string,mixed>>|null $lines */
    private static function sumRepeater(?array $lines, string $field): float
    {
        return round(collect($lines ?? [])->sum(fn (array $line): float => (float) ($line[$field] ?? 0)), 2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('invoice_number')->searchable(),
            Tables\Columns\TextColumn::make('company.name')->label('Company'),
            Tables\Columns\TextColumn::make('receivingWarehouse.name')->label('Receiving Warehouse'),
            Tables\Columns\TextColumn::make('supplier.name'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('gross_total')->label('Total')->money('EUR'),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
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
