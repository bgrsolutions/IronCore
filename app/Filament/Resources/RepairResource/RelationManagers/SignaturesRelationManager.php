<?php

namespace App\Filament\Resources\RepairResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SignaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'signatures';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('signature_type'),
                Tables\Columns\TextColumn::make('signer_name'),
                Tables\Columns\TextColumn::make('signed_at')->dateTime(),
                Tables\Columns\ImageColumn::make('signature_image_path')->disk(config('filesystems.default')),
            ]);
    }
}
