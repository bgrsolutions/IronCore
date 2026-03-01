<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\SalesDocumentResource\Pages;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\SalesDocument;
use App\Models\StoreLocation;
use App\Services\SalesPricingService;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesDocumentResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = SalesDocument::class;

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 10;

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
            Forms\Components\Section::make('Header')
                ->schema([
                    Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
                    Forms\Components\Select::make('store_location_id')
                        ->label('Store')
                        ->options(fn () => StoreLocation::query()->when(! $isManager, fn ($q) => $q->whereIn('id', $storeIds ?: [0]))->pluck('name', 'id'))
                        ->required(! $isManager),
                    Forms\Components\Select::make('customer_id')->options(fn () => Customer::query()->pluck('name', 'id')),
                    Forms\Components\Select::make('doc_type')->options(['ticket' => 'Ticket', 'invoice' => 'Invoice', 'credit_note' => 'Credit Note'])->required(),
                    Forms\Components\TextInput::make('series')->required()->default('T'),
                    Forms\Components\Select::make('source')->options(['manual' => 'Manual', 'pos' => 'POS', 'prestashop' => 'PrestaShop'])->default('manual'),
                    Forms\Components\DateTimePicker::make('issue_date')->default(now())->required(),
                    Forms\Components\Select::make('tax_mode')
                        ->options([
                            'inherit_company' => 'Inherit Company',
                            'tax_exempt' => 'Tax Exempt',
                            'custom' => 'Custom',
                        ])
                        ->default('inherit_company')
                        ->required()
                        ->reactive(),
                    Forms\Components\TextInput::make('tax_rate')
                        ->label('Custom Tax %')
                        ->numeric()
                        ->visible(fn (callable $get) => $get('tax_mode') === 'custom'),
                ])->columns(3),

            Forms\Components\Section::make('Lines')
                ->description('Clean table-style line editor. Product selection and recalculation are powered by SalesPricingService.')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->addActionLabel('Add line')
                        ->reorderable(false)
                        ->schema([
                            Forms\Components\TextInput::make('line_no')->label('#')->numeric()->required()->default(1),
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")
                                    ->orWhere('ean', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Product $p): array => [$p->id => trim(($p->name ?? '').' | '.($p->sku ?? 'n/a').' | '.($p->ean ?? 'n/a'))])
                                    ->all())
                                ->getOptionLabelUsing(fn ($value): ?string => optional(Product::query()->find($value), fn (Product $p): string => trim(($p->name ?? '').' | '.($p->sku ?? 'n/a').' | '.($p->ean ?? 'n/a')))
                                )
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                                    if (! $state) {
                                        return;
                                    }

                                    $companyId = (int) ($get('../../company_id') ?? CompanyContext::get());
                                    $company = Company::query()->find($companyId);
                                    $product = Product::query()->find((int) $state);
                                    if (! $company || ! $product) {
                                        return;
                                    }

                                    $qty = (float) ($get('qty') ?? 1);
                                    $pricing = app(SalesPricingService::class)->calculateLineForProduct(
                                        $product,
                                        $company,
                                        $qty,
                                        $get('../../tax_mode'),
                                        $get('../../tax_rate') !== null ? (float) $get('../../tax_rate') : null
                                    );

                                    $set('description', $get('description') ?: $product->name);
                                    $set('unit_price', $pricing['unit_price']);
                                    $set('tax_rate', $pricing['tax_rate']);
                                    $set('line_net', $pricing['line_net']);
                                    $set('line_tax', $pricing['line_tax']);
                                    $set('line_gross', $pricing['line_gross']);
                                }),
                            Forms\Components\TextInput::make('description')->required()->columnSpan(2),
                            Forms\Components\TextInput::make('qty')
                                ->label('Qty')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(0)
                                ->step(1)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => static::recalculateManualLine($get, $set)),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Selling (net)')
                                ->numeric()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => static::recalculateManualLine($get, $set))
                                ->helperText('Validation prevents below-landed-cost pricing.'),
                            Forms\Components\TextInput::make('line_net')->label('Net')->numeric()->required(),
                            Forms\Components\TextInput::make('tax_rate')->label('Tax %')->numeric()->required()->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => static::recalculateManualLine($get, $set)),
                            Forms\Components\TextInput::make('line_tax')->label('Tax')->numeric()->required(),
                            Forms\Components\TextInput::make('line_gross')->label('Total')->numeric()->required(),
                            Forms\Components\TextInput::make('margin_indicator')
                                ->label('Margin indicator')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(function (callable $get): string {
                                    $lineNet = (float) $get('line_net');
                                    $estimatedCost = $get('cost_total') !== null
                                        ? (float) $get('cost_total')
                                        : (((float) ProductCost::query()
                                            ->where('company_id', (int) CompanyContext::get())
                                            ->where('product_id', (int) ($get('product_id') ?? 0))
                                            ->value('avg_cost')) * abs((float) $get('qty')));

                                    $profit = round($lineNet - $estimatedCost, 2);

                                    return $profit >= 0
                                        ? 'Profit +'.number_format($profit, 2)
                                        : 'Loss '.number_format($profit, 2);
                                }),
                        ])->columns(10)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Totals')
                ->schema([
                    Forms\Components\Placeholder::make('sales_subtotal')
                        ->label('Subtotal (Net)')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'line_net'), 2)),
                    Forms\Components\Placeholder::make('sales_tax_total')
                        ->label('Tax')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'line_tax'), 2)),
                    Forms\Components\Placeholder::make('sales_grand_total')
                        ->label('Total')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'line_gross'), 2)),
                    Forms\Components\Placeholder::make('sales_profit_indicator')
                        ->label('Profit indicator')
                        ->content(function (Get $get): string {
                            $net = self::sumRepeater($get('lines'), 'line_net');
                            $estimated = collect($get('lines') ?? [])->sum(function (array $line): float {
                                $cost = (float) ProductCost::query()
                                    ->where('company_id', (int) CompanyContext::get())
                                    ->where('product_id', (int) ($line['product_id'] ?? 0))
                                    ->value('avg_cost');

                                return $cost * abs((float) ($line['qty'] ?? 0));
                            });

                            $profit = round($net - $estimated, 2);

                            return $profit >= 0
                                ? 'Profit +'.number_format($profit, 2)
                                : 'Loss '.number_format($profit, 2);
                        }),
                ])->columns(4),

            Forms\Components\Textarea::make('below_cost_override_reason')->dehydrated(false),
            Forms\Components\TextInput::make('status')->disabled(),
        ]);
    }

    private static function recalculateManualLine(callable $get, callable $set): void
    {
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $quantity = (float) ($get('qty') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        $totals = app(SalesPricingService::class)->calculateLineTotals($unitPrice, $quantity, $taxRate);
        $set('line_net', $totals['line_net']);
        $set('line_tax', $totals['line_tax']);
        $set('line_gross', $totals['line_gross']);
    }

    /** @param array<int, array<string,mixed>>|null $lines */
    private static function sumRepeater(?array $lines, string $field): float
    {
        return round(collect($lines ?? [])->sum(fn (array $line): float => (float) ($line[$field] ?? 0)), 2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('full_number')->label('Number')->searchable(),
            Tables\Columns\TextColumn::make('storeLocation.name')->label('Store'),
            Tables\Columns\TextColumn::make('doc_type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('issue_date')->dateTime(),
            Tables\Columns\TextColumn::make('gross_total')->label('Total')->money('EUR'),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSalesDocuments::route('/'), 'create' => Pages\CreateSalesDocument::route('/create'), 'edit' => Pages\EditSalesDocument::route('/{record}/edit')];
    }
}
