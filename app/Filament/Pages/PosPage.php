<?php

namespace App\Filament\Pages;

use App\Services\SalesDocumentService;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\SalesDocument;
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

    protected static ?string $navigationLabel = 'POS';

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
                Select::make('product_id')->options(Product::query()->pluck('name', 'id'))->searchable(),
                TextInput::make('description')->required(),
                TextInput::make('qty')->numeric()->default(1),
                TextInput::make('unit_price')->numeric()->default(0),
                TextInput::make('tax_rate')->numeric()->default(7),
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
            'created_by_user_id' => auth()->id(),
        ]);

        foreach ($this->lines as $i => $line) {
            $qty = (float) ($line['qty'] ?? 1);
            $unit = (float) ($line['unit_price'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? 7);
            $net = round($qty * $unit, 2);
            $tax = round($net * ($taxRate / 100), 2);
            $gross = round($net + $tax, 2);
            $doc->lines()->create([
                'line_no' => $i + 1,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'] ?? 'POS item',
                'qty' => $qty,
                'unit_price' => $unit,
                'tax_rate' => $taxRate,
                'line_net' => $net,
                'line_tax' => $tax,
                'line_gross' => $gross,
            ]);
        }

        app(SalesDocumentService::class)->post($doc, $this->below_cost_override_reason);
        Notification::make()->success()->title('Ticket posted')->send();
    }
}
