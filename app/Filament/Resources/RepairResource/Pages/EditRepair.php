<?php

namespace App\Filament\Resources\RepairResource\Pages;

use App\Domain\Repairs\RepairWorkflowService;
use App\Filament\Resources\RepairResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRepair extends EditRecord
{
    protected static string $resource = RepairResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $targetStatus = (string) ($data['status'] ?? $record->status);

        if ($targetStatus !== $record->status) {
            try {
                app(RepairWorkflowService::class)->transitionModel($record, $targetStatus, auth()->id() ?? 0, 'Filament update');
            } catch (\RuntimeException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();
                $data['status'] = $record->status;
            }
        }

        return $data;
    }
}
