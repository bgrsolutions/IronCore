<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource\RelationManagers\DocumentAttachmentsRelationManager;
use App\Models\Expense;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Expense::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\TextInput::make('merchant')->required(),
            Forms\Components\DatePicker::make('date')->required(),
            Forms\Components\TextInput::make('category')->required(),
            Forms\Components\Repeater::make('lines')->relationship('lines')->schema([
                Forms\Components\TextInput::make('description')->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->default(1),
                Forms\Components\TextInput::make('unit_price')->numeric()->default(0),
                Forms\Components\TextInput::make('net_amount')->numeric()->default(0),
                Forms\Components\TextInput::make('tax_amount')->numeric()->default(0),
                Forms\Components\TextInput::make('gross_amount')->numeric()->default(0),
                Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            ])->columns(3),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('cancel_reason')->disabled(fn (callable $get) => $get('status') !== 'cancelled'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('merchant')->searchable(),
            Tables\Columns\TextColumn::make('category'),
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
        return ['index' => Pages\ListExpenses::route('/'), 'create' => Pages\CreateExpense::route('/create'), 'edit' => Pages\EditExpense::route('/{record}/edit')];
    }
}
