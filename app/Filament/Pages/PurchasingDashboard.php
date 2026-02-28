<?php

namespace App\Filament\Pages;

use App\Models\PurchasePlan;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class PurchasingDashboard extends Page
{
    protected static ?string $navigationGroup = 'Purchasing';
    protected static ?string $navigationLabel = 'Purchasing Dashboard';
    protected static ?string $slug = 'purchasing/dashboard';
    protected static string $view = 'filament.pages.purchasing-dashboard';

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $openPlans = PurchasePlan::query()->with('supplier', 'items')->where('company_id', $companyId)->whereIn('status', ['draft', 'ordered', 'partially_received'])->get();
        $latePlans = $openPlans->filter(fn ($plan) => $plan->expected_at && $plan->expected_at->isPast());
        $openExposure = $openPlans->sum(fn ($plan) => $plan->items->sum(fn ($i) => (float) ($i->ordered_qty ?? $i->suggested_qty) * (float) ($i->unit_cost_estimate ?? 0)));

        return [
            'openPlans' => $openPlans,
            'latePlans' => $latePlans,
            'openExposure' => round((float) $openExposure, 2),
        ];
    }
}
