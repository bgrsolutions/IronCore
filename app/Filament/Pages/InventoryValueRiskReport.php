<?php

namespace App\Filament\Pages;

use App\Models\ProductCost;
use App\Models\StockMove;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class InventoryValueRiskReport extends Page
{
    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Inventory Value & Risk';

    protected static ?string $slug = 'reports/inventory-value-risk';

    protected static string $view = 'filament.pages.inventory-value-risk-report';

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $costs = ProductCost::query()->where('company_id', $companyId)->get()->keyBy('product_id');

        $onHand = StockMove::query()
            ->where('company_id', $companyId)
            ->selectRaw('product_id, warehouse_id, SUM(qty) as qty')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        $warehouseValue = [];
        $negativeRows = [];
        foreach ($onHand as $row) {
            $avg = (float) optional($costs->get($row->product_id))->avg_cost;
            $qty = (float) $row->qty;
            $value = $qty * $avg;
            $warehouseValue[$row->warehouse_id] = ($warehouseValue[$row->warehouse_id] ?? 0) + $value;

            if ($qty < 0) {
                $negativeRows[] = ['product_id' => (int) $row->product_id, 'warehouse_id' => (int) $row->warehouse_id, 'on_hand' => $qty, 'exposure' => round(abs($qty) * $avg, 2)];
            }
        }

        $dead = StockMove::query()
            ->where('company_id', $companyId)
            ->selectRaw('product_id, MAX(occurred_at) as last_moved_at')
            ->groupBy('product_id')
            ->get()
            ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'last_moved_at' => (string) $r->last_moved_at, 'days' => now()->diffInDays($r->last_moved_at)])
            ->filter(fn (array $r) => $r['days'] >= 60)
            ->values()
            ->all();

        return ['warehouseValue' => $warehouseValue, 'negativeRows' => $negativeRows, 'deadRows' => $dead];
    }
}
