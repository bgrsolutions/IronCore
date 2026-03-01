<?php

namespace App\Filament\Resources\VendorBillResource\Pages;

use App\Domain\Inventory\VendorBillStockIntegrationService;
use App\Filament\Resources\VendorBillResource;
use App\Models\AuditLog;
use App\Services\PurchasePlanService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditVendorBill extends EditRecord
{
    protected static string $resource = VendorBillResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->check() && ! auth()->user()->isManagerOrAdmin()) {
            $allowed = auth()->user()->assignedStoreLocationIds();
            if (! in_array((int) ($data['store_location_id'] ?? $this->record->store_location_id), $allowed, true)) {
                throw ValidationException::withMessages(['store_location_id' => 'You are not assigned to this store.']);
            }
        }

        if ($this->record->locked_at && ($this->record->status !== 'cancelled')) {
            throw ValidationException::withMessages(['status' => 'Posted vendor bills are locked.']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')->visible(fn () => $this->record->status === 'draft')->action(function (): void {
                $this->record->update(['status' => 'approved']);
                AuditLog::create(['company_id' => $this->record->company_id, 'user_id' => auth()->id(), 'action' => 'vendor_bill.approved', 'auditable_type' => 'vendor_bill', 'auditable_id' => $this->record->id]);
                Notification::make()->title('Approved')->success()->send();
            }),
            Actions\Action::make('post')->visible(fn () => $this->record->status === 'approved')->action(function (): void {
                $this->record->update(['status' => 'posted', 'posted_at' => now(), 'locked_at' => now()]);

                app(VendorBillStockIntegrationService::class)->receiveForPostedBill($this->record->fresh('lines'));

                $totals = $this->record->fresh()->lines()->selectRaw('COALESCE(SUM(net_amount),0) net, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(gross_amount),0) gross')->first();
                $this->record->update(['net_total' => $totals->net, 'tax_total' => $totals->tax, 'gross_total' => $totals->gross]);

                app(PurchasePlanService::class)->syncReceivedFromVendorBill($this->record->fresh('lines'));
                AuditLog::create(['company_id' => $this->record->company_id, 'user_id' => auth()->id(), 'action' => 'vendor_bill.posted', 'auditable_type' => 'vendor_bill', 'auditable_id' => $this->record->id]);
                Notification::make()->title('Posted and locked')->success()->send();
            }),
            Actions\Action::make('cancel')
                ->visible(fn () => $this->record->status === 'posted')
                ->form([\Filament\Forms\Components\Textarea::make('cancel_reason')->required()])
                ->action(function (array $data): void {
                    $this->record->update(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason'], 'cancelled_at' => now()]);
                    AuditLog::create(['company_id' => $this->record->company_id, 'user_id' => auth()->id(), 'action' => 'vendor_bill.cancelled', 'auditable_type' => 'vendor_bill', 'auditable_id' => $this->record->id, 'payload' => ['reason' => $data['cancel_reason']]]);
                }),
        ];
    }
}
