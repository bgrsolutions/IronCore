<?php

namespace App\Filament\Resources;

use App\Domain\Billing\SubscriptionBillingService;
use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Subscription::class;


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
            Forms\Components\Select::make('customer_id')->options(Customer::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('plan_id')->options(SubscriptionPlan::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'paused' => 'Paused', 'cancelled' => 'Cancelled'])->default('active')->required(),
            Forms\Components\DateTimePicker::make('starts_at')->required()->default(now()),
            Forms\Components\DateTimePicker::make('next_run_at')->required()->default(now()),
            Forms\Components\DateTimePicker::make('ends_at'),
            Forms\Components\Textarea::make('cancel_reason'),
            Forms\Components\Toggle::make('auto_post'),
            Forms\Components\Select::make('doc_type')->options(['ticket' => 'Ticket', 'invoice' => 'Invoice']),
            Forms\Components\TextInput::make('series'),
            Forms\Components\TextInput::make('price_net')->numeric(),
            Forms\Components\TextInput::make('tax_rate')->numeric(),
            Forms\Components\TextInput::make('currency')->default('EUR')->required(),
            Forms\Components\Textarea::make('notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id'),
            Tables\Columns\TextColumn::make('customer.name')->label('Customer'),
            Tables\Columns\TextColumn::make('plan.name')->label('Plan'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('next_run_at')->dateTime(),
            Tables\Columns\TextColumn::make('runs.status')->label('Last run')->getStateUsing(fn (Subscription $r) => $r->runs()->latest('run_at')->value('status') ?? '-'),
            Tables\Columns\TextColumn::make('runs.generated_sales_document_id')->label('Last doc')->getStateUsing(fn (Subscription $r) => $r->runs()->latest('run_at')->value('generated_sales_document_id') ?? '-'),
        ])->actions([
            Tables\Actions\Action::make('pause')->visible(fn () => auth()->user()?->hasAnyRole(['admin', 'manager']))->action(fn (Subscription $record) => $record->update(['status' => 'paused'])),
            Tables\Actions\Action::make('resume')->visible(fn () => auth()->user()?->hasAnyRole(['admin', 'manager']))->action(fn (Subscription $record) => $record->update(['status' => 'active'])),
            Tables\Actions\Action::make('cancel')->visible(fn () => auth()->user()?->hasAnyRole(['admin', 'manager']))
                ->form([Forms\Components\Textarea::make('cancel_reason')->required()])
                ->action(function (Subscription $record, array $data): void {
                    $record->update(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason']]);
                }),
            Tables\Actions\Action::make('run_now')->visible(fn () => auth()->user()?->hasAnyRole(['admin', 'manager']))
                ->action(function (Subscription $record): void {
                    app(SubscriptionBillingService::class)->generateInvoiceForSubscription($record->fresh(), now());
                    Notification::make()->success()->title('Subscription run created.')->send();
                }),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
