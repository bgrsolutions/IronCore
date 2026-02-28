<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\StockService;
use App\Models\ProductCompany;
use App\Models\ProductCost;
use App\Models\StockMove;
use App\Models\InventoryAlert;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class InventoryDashboard extends Page
{
    protected static ?string $navigationLabel = 'Inventory Dashboard';

    protected static ?string $slug = 'inventory-dashboard';

    protected static string $view = 'filament.pages.inventory-dashboard';

    protected function getViewData(): array
    {
        $companyId = CompanyContext::get();
        $service = app(StockService::class);

        $rows = [];
        $stockValue = 0.0;
        $lowStock = 0;

        foreach (ProductCompany::query()->with('product')->get() as $pc) {
            $onHand = $service->getOnHand($companyId, $pc->product_id);
            $avg = (float) optional(ProductCost::query()->where('company_id', $companyId)->where('product_id', $pc->product_id)->first())->avg_cost;
            $rows[] = ['product' => $pc->product->name, 'on_hand' => $onHand, 'reorder_min_qty' => $pc->reorder_min_qty ?? 0, 'avg_cost' => round($avg, 4)];
            $stockValue += $onHand * $avg;
            if ($pc->reorder_min_qty !== null && $onHand < (float) $pc->reorder_min_qty) {
                $lowStock++;
            }
        }

        $recentReceipts = StockMove::query()->where('company_id', $companyId)->where('move_type', 'receipt')->where('occurred_at', '>=', now()->subDays(14))->count();
        $negativeAlerts = InventoryAlert::query()->where('company_id', $companyId)->where('alert_type', 'negative_stock')->count();
        $negativeExposure = 0.0;
        foreach ($rows as $row) {
            if (($row['on_hand'] ?? 0) < 0) {
                $negativeExposure += abs((float) $row['on_hand']) * (float) ($row['avg_cost'] ?? 0);
            }
        }

        return ['stockValue' => round($stockValue, 2), 'lowStockCount' => $lowStock, 'recentReceipts' => $recentReceipts, 'negativeAlerts' => $negativeAlerts, 'negativeExposure' => round($negativeExposure, 2), 'onHandRows' => $rows];
    }
}
