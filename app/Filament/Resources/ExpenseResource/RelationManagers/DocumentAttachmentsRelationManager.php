<?php

namespace App\Filament\Resources\ExpenseResource\RelationManagers;

use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentAttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documentAttachments';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('document_id')
                ->label('Document')
                ->options(Document::query()->where('company_id', $this->ownerRecord->company_id)->pluck('original_name', 'id'))
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document.original_name')->label('File'),
                Tables\Columns\TextColumn::make('document.mime_type'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->mutateFormDataUsing(fn (array $data) => $data + ['company_id' => $this->ownerRecord->company_id])])
            ->actions([
                Tables\Actions\Action::make('download')->url(fn ($record) => route('documents.download', $record->document_id), shouldOpenInNewTab: true),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
