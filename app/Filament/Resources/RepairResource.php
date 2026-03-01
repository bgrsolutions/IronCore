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
use App\Models\StoreLocation;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RepairResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Repair::class;

    protected static ?string $navigationGroup = 'Repairs';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 20;

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
            Forms\Components\Placeholder::make('time_leak_warning')
                ->label('⚠ Repair Time Leakage Alert')
                ->content('Time logged but no labour billed.')
                ->visible(fn (?Repair $record): bool => $record ? app(RepairWorkflowService::class)->isTimeLeakBlocked($record) : false),
            Forms\Components\Select::make('store_location_id')
                ->label('Store')
                ->options(fn () => StoreLocation::query()->when(! $isManager, fn ($q) => $q->whereIn('id', $storeIds ?: [0]))->pluck('name', 'id'))
                ->required(! $isManager)
                ->reactive(),
            Forms\Components\Select::make('customer_id')->options(Customer::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\TextInput::make('status')->required()->default('intake'),
            Forms\Components\Select::make('technician_user_id')
                ->label('Technician')
                ->options(function (callable $get) use ($isManager) {
                    $storeId = (int) ($get('store_location_id') ?? 0);
                    $query = User::query();
                    if (! $isManager && $storeId > 0) {
                        $query->whereHas('storeLocations', fn ($q) => $q->where('store_locations.id', $storeId));
                    }

                    return $query->pluck('name', 'id');
                })
                ->searchable(),
            Forms\Components\Textarea::make('status_change_reason')->dehydrated(false)->rows(2),
            Forms\Components\Select::make('linked_sales_document_id')->label('Linked sales document')->options(SalesDocument::query()->pluck('full_number', 'id'))->searchable(),
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
            Tables\Columns\TextColumn::make('storeLocation.name')->label('Store'),
            Tables\Columns\TextColumn::make('customer.name')->label('Customer'),
            Tables\Columns\TextColumn::make('technician.name')->label('Technician'),
            Tables\Columns\BadgeColumn::make('status'),
        ])->actions([
            Tables\Actions\Action::make('intake_link')->label('Generate Intake Signature Link')->action(function (Repair $record): void {
                $token = app(RepairPublicFlowService::class)->generateToken($record, 'repair_intake_signature');
                Notification::make()->success()->title(route('public.repairs.show', $token->token))->send();
            }),
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
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
