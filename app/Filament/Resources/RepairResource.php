<?php

namespace App\Filament\Resources;

use App\Domain\Repairs\RepairPublicFlowService;
use App\Domain\Repairs\RepairWorkflowService;
use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\RepairResource\Pages;
use App\Filament\Resources\RepairResource\RelationManagers\SignaturesRelationManager;
use App\Models\Customer;
use App\Models\Repair;
use App\Models\SalesDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RepairResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Repair::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('time_leak_warning')
                ->label('⚠ Repair Time Leakage Alert')
                ->content('Time logged but no labour billed.')
                ->visible(function (?Repair $record): bool {
                    if (! $record) {
                        return false;
                    }

                    return app(RepairWorkflowService::class)->isTimeLeakBlocked($record);
                }),
            Forms\Components\Select::make('customer_id')->options(Customer::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\TextInput::make('status')->required()->default('intake'),
            Forms\Components\Textarea::make('status_change_reason')
                ->dehydrated(false)
                ->rows(2)
                ->helperText('Required for manager/admin when overriding time-leak transition blocks.'),
            Forms\Components\Select::make('linked_sales_document_id')
                ->label('Linked sales document')
                ->options(SalesDocument::query()->pluck('full_number', 'id'))
                ->searchable(),
            Forms\Components\TextInput::make('device_brand'),
            Forms\Components\TextInput::make('device_model'),
            Forms\Components\TextInput::make('serial_number'),
            Forms\Components\Textarea::make('reported_issue'),
            Forms\Components\Textarea::make('internal_notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('Ref'),
            Tables\Columns\TextColumn::make('customer.name')->label('Customer'),
            Tables\Columns\TextColumn::make('device_brand'),
            Tables\Columns\TextColumn::make('device_model'),
            Tables\Columns\BadgeColumn::make('status'),
        ])->actions([
            Tables\Actions\Action::make('intake_link')
                ->label('Generate Intake Signature Link')
                ->action(function (Repair $record): void {
                    $token = app(RepairPublicFlowService::class)->generateToken($record, 'repair_intake_signature');
                    Notification::make()->success()->title(route('public.repairs.show', $token->token))->send();
                }),
            Tables\Actions\Action::make('pickup_link')
                ->label('Generate Pickup Signature Link')
                ->action(function (Repair $record): void {
                    $token = app(RepairPublicFlowService::class)->generateToken($record, 'repair_pickup_signature');
                    Notification::make()->success()->title(route('public.repairs.show', $token->token))->send();
                }),
            Tables\Actions\Action::make('feedback_link')
                ->label('Generate Feedback Link')
                ->action(function (Repair $record): void {
                    $token = app(RepairPublicFlowService::class)->generateToken($record, 'repair_feedback');
                    Notification::make()->success()->title(route('public.repairs.show', $token->token))->send();
                }),
            Tables\Actions\Action::make('pickup_receipt')
                ->label('Generate Pickup Receipt PDF')
                ->action(function (Repair $record): void {
                    app(RepairPublicFlowService::class)->generatePickupReceipt($record);
                    Notification::make()->success()->title('Pickup receipt generated and attached.')->send();
                }),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [SignaturesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepairs::route('/'),
            'create' => Pages\CreateRepair::route('/create'),
            'edit' => Pages\EditRepair::route('/{record}/edit'),
        ];
    }
}
