<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\SalesDocument;
use App\Services\SalesDocumentService;
use App\Services\SalesPricingService;
use App\Support\Company\CompanyContext;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PosPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'POS';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'pos';

    protected static string $view = 'filament.pages.pos-page';

    public ?int $customer_id = null;

    public ?string $barcode = null;

    public ?string $below_cost_override_reason = null;

    /** @var array<int, array<string,mixed>> */
    public array $lines = [];

    protected function getFormSchema(): array
    {
        return [
            Select::make('customer_id')->options(Customer::query()->pluck('name', 'id'))->searchable()->label('Customer (optional)'),
            TextInput::make('barcode')->label('Barcode quick entry'),
            Repeater::make('lines')->schema([
                Select::make('product_id')
                    ->options(Product::query()->pluck('name', 'id'))
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                        if (! $state) {
                            return;
                        }

                        $company = Company::query()->find((int) CompanyContext::get());
                        $product = Product::query()->find((int) $state);
                        if (! $company || ! $product) {
                            return;
                        }

                        $qty = (float) ($get('qty') ?? 1);
                        $pricing = app(SalesPricingService::class)->calculateLineForProduct(
                            $product,
                            $company,
                            $qty,
                            'inherit_company',
                            null
                        );

                        $set('description', $get('description') ?: $product->name);
                        $set('unit_price', $pricing['unit_price']);
                        $set('tax_rate', $pricing['tax_rate']);
                        $set('line_net', $pricing['line_net']);
                        $set('line_tax', $pricing['line_tax']);
                        $set('line_gross', $pricing['line_gross']);
                    }),
                TextInput::make('description')->required(),
                TextInput::make('qty')->numeric()->default(1)->reactive()
                    ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLine($get, $set)),
                TextInput::make('unit_price')->numeric()->default(0)->reactive()
                    ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLine($get, $set)),
                TextInput::make('tax_rate')->numeric()->default(0)->reactive()
                    ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLine($get, $set)),
                TextInput::make('line_net')->numeric()->disabled()->dehydrated(false),
                TextInput::make('line_tax')->numeric()->disabled()->dehydrated(false),
                TextInput::make('line_gross')->numeric()->disabled()->dehydrated(false),
                TextInput::make('margin_estimate')
                    ->label('Margin estimate')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(function (callable $get): string {
                        $lineNet = (float) $get('line_net');
                        if ($lineNet === 0.0) {
                            $qty = (float) $get('qty');
                            $unit = (float) $get('unit_price');
                            $lineNet = round($qty * $unit, 2);
                        }

                        $productId = $get('product_id');
                        $estimatedCost = $productId
                            ? ((float) ProductCost::query()->where('company_id', (int) CompanyContext::get())->where('product_id', (int) $productId)->value('avg_cost') * abs((float) $get('qty')))
                            : 0.0;

                        return number_format($lineNet - $estimatedCost, 2);
                    }),
            ])->columns(5),
            TextInput::make('below_cost_override_reason')
                ->label('Below-cost override reason (manager/admin only)')
                ->maxLength(500),
        ];
    }

    private static function recalculateLine(callable $get, callable $set): void
    {
        $totals = app(SalesPricingService::class)->calculateLineTotals(
            (float) ($get('unit_price') ?? 0),
            (float) ($get('qty') ?? 0),
            (float) ($get('tax_rate') ?? 0)
        );

        $set('line_net', $totals['line_net']);
        $set('line_tax', $totals['line_tax']);
        $set('line_gross', $totals['line_gross']);
    }

    public function postTicket(): void
    {
        $doc = SalesDocument::create([
            'company_id' => CompanyContext::get(),
            'customer_id' => $this->customer_id,
            'doc_type' => 'ticket',
            'series' => 'T',
            'status' => 'draft',
            'issue_date' => now(),
            'source' => 'pos',
            'tax_mode' => 'inherit_company',
            'tax_rate' => null,
            'created_by_user_id' => auth()->id(),
        ]);

        $company = Company::query()->findOrFail((int) CompanyContext::get());

        foreach ($this->lines as $i => $line) {
            $qty = (float) ($line['qty'] ?? 1);
            $unit = (float) ($line['unit_price'] ?? 0);
            $taxRate = isset($line['tax_rate']) ? (float) $line['tax_rate'] : app(SalesPricingService::class)->resolveSalesTaxRate('inherit_company', null, $company);

            if (! empty($line['product_id'])) {
                $product = Product::query()->find((int) $line['product_id']);
                if ($product) {
                    $landedCost = app(SalesPricingService::class)->calculateLandedCost($product, $company);
                    app(SalesPricingService::class)->enforceMinimumPrice($unit, $landedCost);
                }
            }

            $totals = app(SalesPricingService::class)->calculateLineTotals($unit, $qty, $taxRate);

            $doc->lines()->create([
                'line_no' => $i + 1,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'] ?? 'POS item',
                'qty' => $qty,
                'unit_price' => $unit,
                'tax_rate' => $taxRate,
                'line_net' => $totals['line_net'],
                'line_tax' => $totals['line_tax'],
                'line_gross' => $totals['line_gross'],
            ]);
        }

        app(SalesDocumentService::class)->post($doc, $this->below_cost_override_reason);
        Notification::make()->success()->title('Ticket posted')->send();
    }
}
