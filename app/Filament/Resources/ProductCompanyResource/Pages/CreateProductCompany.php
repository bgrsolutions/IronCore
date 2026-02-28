<?php

namespace App\Filament\Resources\ProductCompanyResource\Pages;

use App\Filament\Resources\ProductCompanyResource;
use App\Models\ProductReorderSetting;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCompany extends CreateRecord
{
    protected static string $resource = ProductCompanyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->persistReorderSettings($data);

        unset(
            $data['reorder_is_enabled'],
            $data['reorder_lead_time_days'],
            $data['reorder_safety_days'],
            $data['reorder_min_days_cover'],
            $data['reorder_max_days_cover'],
            $data['reorder_min_order_qty'],
            $data['reorder_pack_size_qty'],
            $data['reorder_preferred_supplier_id'],
        );

        return $data;
    }

    private function persistReorderSettings(array $data): void
    {
        ProductReorderSetting::query()->updateOrCreate(
            ['company_id' => (int) $data['company_id'], 'product_id' => (int) $data['product_id']],
            [
                'is_enabled' => (bool) ($data['reorder_is_enabled'] ?? true),
                'lead_time_days' => (int) ($data['reorder_lead_time_days'] ?? 3),
                'safety_days' => (int) ($data['reorder_safety_days'] ?? 7),
                'min_days_cover' => (int) ($data['reorder_min_days_cover'] ?? 14),
                'max_days_cover' => (int) ($data['reorder_max_days_cover'] ?? 30),
                'min_order_qty' => $data['reorder_min_order_qty'] ?? null,
                'pack_size_qty' => $data['reorder_pack_size_qty'] ?? null,
                'preferred_supplier_id' => $data['reorder_preferred_supplier_id'] ?? null,
            ]
        );
    }
}
