<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\VerifactuExportResource\Pages;
use App\Models\VerifactuExport;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VerifactuExportResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = VerifactuExport::class;

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?string $navigationLabel = 'VeriFactu Exports';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('period_start')->dateTime()->label('From'),
                Tables\Columns\TextColumn::make('period_end')->dateTime()->label('To'),
                Tables\Columns\TextColumn::make('record_count')->sortable(),
                Tables\Columns\TextColumn::make('file_hash')->limit(24),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('generated_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->url(fn (VerifactuExport $record) => route('verifactu-exports.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVerifactuExports::route('/'),
        ];
    }
}
