<?php

namespace App\Filament\Resources\RepairResource\Pages;

use App\Domain\Repairs\RepairWorkflowService;
use App\Filament\Resources\RepairResource;
use App\Models\AuditLog;
use App\Services\RepairMetricsService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRepair extends EditRecord
{
    protected static string $resource = RepairResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        if (auth()->check() && ! auth()->user()->isManagerOrAdmin()) {
            $allowed = auth()->user()->assignedStoreLocationIds();
            if (! in_array((int) ($data['store_location_id'] ?? $record->store_location_id), $allowed, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['store_location_id' => 'You are not assigned to this store.']);
            }
        }
        $targetStatus = (string) ($data['status'] ?? $record->status);
        $reason = $data['status_change_reason'] ?? 'Filament update';

        if ($targetStatus !== $record->status) {
            try {
                app(RepairWorkflowService::class)->transitionModel($record, $targetStatus, auth()->id() ?? 0, $reason);
            } catch (\RuntimeException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();
                $data['status'] = $record->status;
            }
        }

        unset($data['status_change_reason']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_labour_15m')
                ->label('Add Labour 15m')
                ->color('warning')
                ->visible(fn (): bool => app(RepairWorkflowService::class)->isTimeLeakBlocked($this->getRecord()))
                ->action(fn () => $this->quickAddLabour(15)),
            Actions\Action::make('add_labour_30m')
                ->label('Add Labour 30m')
                ->color('warning')
                ->visible(fn (): bool => app(RepairWorkflowService::class)->isTimeLeakBlocked($this->getRecord()))
                ->action(fn () => $this->quickAddLabour(30)),
            Actions\Action::make('add_labour_60m')
                ->label('Add Labour 60m')
                ->color('warning')
                ->visible(fn (): bool => app(RepairWorkflowService::class)->isTimeLeakBlocked($this->getRecord()))
                ->action(fn () => $this->quickAddLabour(60)),
        ];
    }

    private function quickAddLabour(int $minutes): void
    {
        $repair = $this->getRecord();
        app(RepairMetricsService::class)->addQuickLabourLine($repair, $minutes);

        AuditLog::query()->create([
            'company_id' => $repair->company_id,
            'user_id' => auth()->id(),
            'action' => 'repair.labour_quick_add',
            'auditable_type' => 'repair',
            'auditable_id' => $repair->id,
            'payload' => [
                'minutes' => $minutes,
                'event_type' => 'repair_labour_quick_add',
            ],
            'created_at' => now(),
        ]);

        Notification::make()->success()->title(sprintf('Added labour line (%d minutes).', $minutes))->send();
    }
}
