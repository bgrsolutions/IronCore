<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerCompaniesRelationManager extends RelationManager
{
    protected static string $relationship = 'companies';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')->options(Company::query()->pluck('name', 'id'))->required(),
            Forms\Components\TextInput::make('fiscal_name'),
            Forms\Components\TextInput::make('tax_id'),
            Forms\Components\Toggle::make('wants_full_invoice')->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name'),
                Tables\Columns\TextColumn::make('fiscal_name'),
                Tables\Columns\IconColumn::make('wants_full_invoice')->boolean(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
