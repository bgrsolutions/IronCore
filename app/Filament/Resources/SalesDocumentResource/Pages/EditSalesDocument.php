<?php

namespace App\Filament\Resources\SalesDocumentResource\Pages;

use App\Services\SalesDocumentService;
use App\Filament\Resources\SalesDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSalesDocument extends EditRecord
{
    protected static string $resource = SalesDocumentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->locked_at) {
            throw ValidationException::withMessages(['status' => 'Posted documents are immutable.']);
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('post')
                ->visible(fn () => $this->record->status === 'draft')
                ->action(fn () => app(SalesDocumentService::class)->post($this->record)),
            Actions\Action::make('cancel_draft')
                ->visible(fn () => $this->record->status === 'draft')
                ->form([\Filament\Forms\Components\Textarea::make('reason')->required()])
                ->action(fn (array $data) => app(SalesDocumentService::class)->cancelDraft($this->record, $data['reason'])),
            Actions\Action::make('create_credit_note')
                ->visible(fn () => $this->record->status === 'posted' && $this->record->doc_type !== 'credit_note')
                ->action(fn () => app(SalesDocumentService::class)->createCreditNote($this->record)),
        ];
    }
}
