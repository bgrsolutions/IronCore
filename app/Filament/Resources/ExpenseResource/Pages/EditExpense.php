<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\AuditLog;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->locked_at && ($this->record->status !== 'cancelled')) {
            throw ValidationException::withMessages(['status' => 'Posted expenses are locked.']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')->visible(fn () => $this->record->status === 'draft')->action(function (): void {
                $this->record->update(['status' => 'approved']);
                AuditLog::create(['company_id' => $this->record->company_id, 'user_id' => auth()->id(), 'action' => 'expense.approved', 'auditable_type' => 'expense', 'auditable_id' => $this->record->id]);
                Notification::make()->title('Approved')->success()->send();
            }),
            Actions\Action::make('post')->visible(fn () => $this->record->status === 'approved')->action(function (): void {
                $totals = $this->record->lines()->selectRaw('COALESCE(SUM(net_amount),0) net, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(gross_amount),0) gross')->first();
                $this->record->update(['status' => 'posted', 'net_total' => $totals->net, 'tax_total' => $totals->tax, 'gross_total' => $totals->gross, 'posted_at' => now(), 'locked_at' => now()]);
                AuditLog::create(['company_id' => $this->record->company_id, 'user_id' => auth()->id(), 'action' => 'expense.posted', 'auditable_type' => 'expense', 'auditable_id' => $this->record->id]);
                Notification::make()->title('Posted and locked')->success()->send();
            }),
            Actions\Action::make('cancel')
                ->visible(fn () => $this->record->status === 'posted')
                ->form([\Filament\Forms\Components\Textarea::make('cancel_reason')->required()])
                ->action(function (array $data): void {
                    $this->record->update(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason'], 'cancelled_at' => now()]);
                    AuditLog::create(['company_id' => $this->record->company_id, 'user_id' => auth()->id(), 'action' => 'expense.cancelled', 'auditable_type' => 'expense', 'auditable_id' => $this->record->id, 'payload' => ['reason' => $data['cancel_reason']]]);
                }),
        ];
    }
}
