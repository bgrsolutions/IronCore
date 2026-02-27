<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = SubscriptionPlan::class;


    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'manager']) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'manager']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Textarea::make('description'),
            Forms\Components\Select::make('plan_type')->options(['subscription' => 'Subscription', 'service_contract' => 'Service Contract'])->default('subscription')->required(),
            Forms\Components\TextInput::make('interval_months')->numeric()->required(),
            Forms\Components\TextInput::make('price_net')->numeric()->required(),
            Forms\Components\TextInput::make('tax_rate')->numeric()->required(),
            Forms\Components\TextInput::make('currency')->default('EUR')->required(),
            Forms\Components\Select::make('default_doc_type')->options(['ticket' => 'Ticket', 'invoice' => 'Invoice'])->default('invoice')->required(),
            Forms\Components\TextInput::make('default_series'),
            Forms\Components\Toggle::make('auto_post'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('plan_type')->badge(),
            Tables\Columns\TextColumn::make('interval_months'),
            Tables\Columns\TextColumn::make('price_net')->money('EUR'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
