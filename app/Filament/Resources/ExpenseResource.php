<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource\RelationManagers\DocumentAttachmentsRelationManager;
use App\Models\Expense;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Expense::class;

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Expense Header')
                ->schema([
                    Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
                    Forms\Components\TextInput::make('merchant')->required(),
                    Forms\Components\DatePicker::make('date')->required(),
                    Forms\Components\TextInput::make('category')->required(),
                    Forms\Components\FileUpload::make('pdf_path')
                        ->label('Expense PDF')
                        ->disk('local')
                        ->directory('expenses')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240),
                ])->columns(2),

            Forms\Components\Section::make('Lines')
                ->description('Table-style entry with automatic Net / Tax / Total calculation.')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->addActionLabel('Add line')
                        ->reorderable(false)
                        ->schema([
                            Forms\Components\TextInput::make('description')
                                ->label('Description')
                                ->required()
                                ->columnSpan(3),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(0)
                                ->step(1)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateExpenseLine($get, $set)),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Cost (purchase)')
                                ->numeric()
                                ->default(0)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateExpenseLine($get, $set)),
                            Forms\Components\TextInput::make('net_amount')
                                ->label('Net')
                                ->numeric()
                                ->default(0)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateExpenseLine($get, $set, true)),
                            Forms\Components\TextInput::make('tax_rate')
                                ->label('Tax %')
                                ->numeric()
                                ->default(0)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateExpenseLine($get, $set)),
                            Forms\Components\TextInput::make('tax_amount')
                                ->label('Tax')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                            Forms\Components\TextInput::make('gross_amount')
                                ->label('Total')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
                        ])
                        ->columns(6)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Totals')
                ->schema([
                    Forms\Components\Placeholder::make('expense_subtotal')
                        ->label('Subtotal (Net)')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'net_amount'), 2)),
                    Forms\Components\Placeholder::make('expense_tax_total')
                        ->label('Tax')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'tax_amount'), 2)),
                    Forms\Components\Placeholder::make('expense_grand_total')
                        ->label('Total')
                        ->content(fn (Get $get): string => number_format(self::sumRepeater($get('lines'), 'gross_amount'), 2)),
                ])->columns(3),

            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('cancel_reason')->disabled(fn (callable $get) => $get('status') !== 'cancelled'),
        ]);
    }

    private static function recalculateExpenseLine(callable $get, callable $set, bool $fromNet = false): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        $net = $fromNet ? (float) ($get('net_amount') ?? 0) : round($quantity * $unitPrice, 2);
        if (! $fromNet) {
            $set('net_amount', $net);
        }

        $tax = round($net * ($taxRate / 100), 2);
        $gross = round($net + $tax, 2);

        $set('tax_amount', $tax);
        $set('gross_amount', $gross);
    }

    /** @param array<int, array<string,mixed>>|null $lines */
    private static function sumRepeater(?array $lines, string $field): float
    {
        return round(collect($lines ?? [])->sum(fn (array $line): float => (float) ($line[$field] ?? 0)), 2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('merchant')->searchable(),
            Tables\Columns\TextColumn::make('category'),
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
        return ['index' => Pages\ListExpenses::route('/'), 'create' => Pages\CreateExpense::route('/create'), 'edit' => Pages\EditExpense::route('/{record}/edit')];
    }
}
