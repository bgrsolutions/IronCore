<?php

namespace App\Filament\Pages;

use App\Models\Repair;
use App\Services\RepairMetricsService;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class RepairProfitabilityReport extends Page
{
    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Repair Profitability';

    protected static ?string $slug = 'reports/repair-profitability';

    protected static string $view = 'filament.pages.repair-profitability-report';

    public bool $only_flagged = false;

    public bool $only_not_invoiced = false;

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $threshold = (int) config('repairs.time_leak_threshold_minutes', 15);
        $metricsService = app(RepairMetricsService::class);

        $rows = Repair::query()->where('company_id', $companyId)->with('linkedSalesDocument')->get()->map(function (Repair $repair) use ($metricsService, $threshold): array {
            $logged = $metricsService->loggedMinutes($repair);
            $hasLabour = $metricsService->hasLabourLines($repair);
            $partsCost = (float) \DB::table('repair_parts')->where('repair_id', $repair->id)->sum('line_cost');
            $billed = (float) optional($repair->linkedSalesDocument)->net_total;
            $timeLeak = $logged > $threshold && ! $hasLabour;

            return [
                'repair_id' => $repair->id,
                'status' => $repair->status,
                'logged_minutes' => $logged,
                'billed_net' => round($billed, 2),
                'parts_cost' => round($partsCost, 2),
                'gross_profit_estimate' => round($billed - $partsCost, 2),
                'time_leak_flag' => $timeLeak,
                'invoiced' => $repair->linked_sales_document_id !== null,
            ];
        })->filter(function (array $row): bool {
            if ($this->only_flagged && ! $row['time_leak_flag']) {
                return false;
            }

            if ($this->only_not_invoiced && $row['invoiced']) {
                return false;
            }

            return true;
        })->values()->all();

        return ['rows' => $rows];
    }
}
