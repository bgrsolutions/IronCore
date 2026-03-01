<?php

namespace App\Filament\Resources\SalesDocumentResource\Pages;

use App\Filament\Resources\SalesDocumentResource;
use App\Models\Company;
use App\Models\Product;
use App\Services\SalesDocumentService;
use App\Services\SalesPricingService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSalesDocument extends EditRecord
{
    protected static string $resource = SalesDocumentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->check() && ! auth()->user()->isManagerOrAdmin()) {
            $allowed = auth()->user()->assignedStoreLocationIds();
            if (! in_array((int) ($data['store_location_id'] ?? $this->record->store_location_id), $allowed, true)) {
                throw ValidationException::withMessages(['store_location_id' => 'You are not assigned to this store.']);
            }
        }

        if ($this->record->locked_at) {
            throw ValidationException::withMessages(['status' => 'Posted documents are immutable.']);
        }

        $company = Company::query()->find((int) ($data['company_id'] ?? $this->record->company_id));
        if ($company) {
            foreach ((array) ($data['lines'] ?? []) as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $product = Product::query()->find($productId);
                if (! $product) {
                    continue;
                }

                $landed = app(SalesPricingService::class)->calculateLandedCost($product, $company);
                $unit = (float) ($line['unit_price'] ?? 0);
                if ($unit + 0.00001 < $landed) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.unit_price" => 'Selling net price cannot be below landed cost.',
                    ]);
                }
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('post')
                ->visible(fn () => $this->record->status === 'draft')
                ->form([
                    \Filament\Forms\Components\Textarea::make('below_cost_override_reason')
                        ->label('Below-cost override reason')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Required for manager/admin if any line is below estimated cost.'),
                ])
                ->action(fn (array $data) => app(SalesDocumentService::class)->post($this->record, $data['below_cost_override_reason'] ?? null)),
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
