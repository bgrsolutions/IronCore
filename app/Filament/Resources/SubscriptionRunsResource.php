<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\SubscriptionRunsResource\Pages;
use App\Models\SubscriptionRun;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionRunsResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = SubscriptionRun::class;
    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 40;



    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'manager', 'staff', 'accountant_readonly']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'manager', 'accountant_readonly']) ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('subscription_id'),
            Tables\Columns\TextColumn::make('run_at')->dateTime(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('message')->limit(40),
            Tables\Columns\TextColumn::make('generated_sales_document_id')->label('Sales doc'),
        ])->defaultSort('run_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionRuns::route('/'),
        ];
    }
}
