<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\SupplierStockSnapshot;
use App\Support\Company\CompanyContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SupplierStockSnapshots extends Page
{
    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Supplier Stock Snapshots';

    protected static ?string $slug = 'reports/supplier-stock-snapshots';

    protected static string $view = 'filament.pages.supplier-stock-snapshots';

    public function createPlaceholdersFromLatest(): void
    {
        $companyId = (int) CompanyContext::get();
        $latest = SupplierStockSnapshot::query()->where('company_id', $companyId)->latest('snapshot_at')->with('items')->first();
        if (! $latest) {
            Notification::make()->danger()->title('No snapshots found')->send();

            return;
        }

        $created = 0;
        foreach ($latest->items->whereNull('product_id') as $item) {
            $product = Product::query()->create([
                'sku' => $item->supplier_sku,
                'barcode' => $item->barcode,
                'name' => $item->product_name ?: 'Placeholder '.$item->id,
                'product_type' => 'stock',
                'is_active' => true,
            ]);
            $item->update(['product_id' => $product->id]);
            $created++;
        }

        Notification::make()->success()->title('Created '.$created.' product placeholders')->send();
    }

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $snapshots = SupplierStockSnapshot::query()
            ->where('company_id', $companyId)
            ->with(['supplier', 'items'])
            ->latest('snapshot_at')
            ->get();

        $rows = $snapshots->map(function (SupplierStockSnapshot $snapshot): array {
            $matched = $snapshot->items->whereNotNull('product_id')->count();
            $unmatched = $snapshot->items->whereNull('product_id')->count();

            return [
                'id' => $snapshot->id,
                'supplier' => $snapshot->supplier?->name,
                'warehouse' => $snapshot->warehouse_name,
                'snapshot_at' => optional($snapshot->snapshot_at)->toDateTimeString(),
                'items_count' => $snapshot->items->count(),
                'matched_count' => $matched,
                'unmatched_count' => $unmatched,
                'items' => $snapshot->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'supplier_sku' => $i->supplier_sku,
                    'barcode' => $i->barcode,
                    'name' => $i->product_name,
                    'qty_available' => (float) $i->qty_available,
                    'unit_cost' => $i->unit_cost !== null ? (float) $i->unit_cost : null,
                ])->all(),
            ];
        })->all();

        return ['rows' => $rows];
    }
}
