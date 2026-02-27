<?php

namespace App\Filament\Pages;

use App\Models\Repair;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class RepairProfitabilityReport extends Page
{
    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Repair Profitability';

    protected static ?string $slug = 'reports/repair-profitability';

    protected static string $view = 'filament.pages.repair-profitability-report';

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();

        $rows = Repair::query()->where('company_id', $companyId)->with('linkedSalesDocument')->get()->map(function (Repair $repair): array {
            $logged = (int) \DB::table('repair_time_entries')->where('repair_id', $repair->id)->sum('minutes');
            $partsCost = (float) \DB::table('repair_parts')->where('repair_id', $repair->id)->sum('line_cost');
            $billed = (float) optional($repair->linkedSalesDocument)->net_total;

            return [
                'repair_id' => $repair->id,
                'status' => $repair->status,
                'logged_minutes' => $logged,
                'billed_net' => round($billed, 2),
                'parts_cost' => round($partsCost, 2),
                'gross_profit_estimate' => round($billed - $partsCost, 2),
            ];
        })->all();

        return ['rows' => $rows];
    }
}
