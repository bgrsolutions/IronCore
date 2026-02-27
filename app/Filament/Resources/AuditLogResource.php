<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = AuditLog::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('action')->searchable(),
            Tables\Columns\TextColumn::make('auditable_type'),
            Tables\Columns\TextColumn::make('user.email'),
        ])->actions([])->bulkActions([]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
