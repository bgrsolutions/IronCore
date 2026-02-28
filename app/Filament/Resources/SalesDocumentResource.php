<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\SalesDocumentResource\Pages;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\SalesDocument;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalesDocumentResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = SalesDocument::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\Select::make('customer_id')->options(fn () => Customer::query()->pluck('name', 'id')),
            Forms\Components\Select::make('doc_type')->options(['ticket' => 'Ticket', 'invoice' => 'Invoice', 'credit_note' => 'Credit Note'])->required(),
            Forms\Components\TextInput::make('series')->required()->default('T'),
            Forms\Components\Select::make('source')->options(['manual' => 'Manual', 'pos' => 'POS', 'prestashop' => 'PrestaShop'])->default('manual'),
            Forms\Components\DateTimePicker::make('issue_date')->default(now())->required(),
            Forms\Components\Repeater::make('lines')->relationship('lines')->schema([
                Forms\Components\TextInput::make('line_no')->numeric()->required(),
                Forms\Components\Select::make('product_id')->options(fn () => Product::query()->pluck('name', 'id'))->searchable(),
                Forms\Components\TextInput::make('description')->required(),
                Forms\Components\TextInput::make('qty')->numeric()->required(),
                Forms\Components\TextInput::make('unit_price')->numeric()->required(),
                Forms\Components\TextInput::make('tax_rate')->numeric()->default(7)->required(),
                Forms\Components\TextInput::make('line_net')->numeric()->required(),
                Forms\Components\TextInput::make('line_tax')->numeric()->required(),
                Forms\Components\TextInput::make('line_gross')->numeric()->required(),
                Forms\Components\TextInput::make('margin_estimate')
                    ->label('Margin estimate')
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

                        return number_format($lineNet - $estimatedCost, 2);
                    }),
            ])->columns(3),
            Forms\Components\Textarea::make('below_cost_override_reason')
                ->label('Below-cost override reason')
                ->dehydrated(false)
                ->rows(2)
                ->maxLength(500)
                ->helperText('Required for manager/admin when any line is below estimated cost.'),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\Textarea::make('cancel_reason')->disabled(),
            Forms\Components\TextInput::make('hash')->disabled()->label('VeriFactu Hash'),
            Forms\Components\TextInput::make('previous_hash')->disabled()->label('Previous Hash'),
            Forms\Components\Textarea::make('qr_payload')->disabled()->rows(3)->label('QR Payload'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('full_number')->label('Number')->searchable(),
            Tables\Columns\TextColumn::make('doc_type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('source'),
            Tables\Columns\TextColumn::make('issue_date')->dateTime(),
            Tables\Columns\TextColumn::make('gross_total')->money('EUR'),
            Tables\Columns\TextColumn::make('hash')->label('Hash')->limit(16)->toggleable(),
            Tables\Columns\TextColumn::make('previous_hash')->label('Prev Hash')->limit(16)->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Tables\Filters\SelectFilter::make('doc_type')->options(['ticket' => 'Ticket', 'invoice' => 'Invoice', 'credit_note' => 'Credit Note']),
            Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'posted' => 'Posted', 'cancelled' => 'Cancelled']),
            Tables\Filters\SelectFilter::make('source')->options(['manual' => 'Manual', 'pos' => 'POS', 'prestashop' => 'PrestaShop']),
            Tables\Filters\Filter::make('issue_date')->form([Forms\Components\DatePicker::make('from'), Forms\Components\DatePicker::make('until')])
                ->query(fn ($query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '>=', $date))
                    ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '<=', $date))),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSalesDocuments::route('/'), 'create' => Pages\CreateSalesDocument::route('/create'), 'edit' => Pages\EditSalesDocument::route('/{record}/edit')];
    }
}
