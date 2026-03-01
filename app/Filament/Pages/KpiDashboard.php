<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class KpiDashboard extends Page
{
    protected static ?string $navigationGroup = 'Reporting';

    protected static ?string $navigationLabel = 'KPI Dashboard';

    protected static ?string $slug = 'reports/kpi-dashboard';

    protected static string $view = 'filament.pages.kpi-dashboard';

    public ?string $from = null;

    public ?string $to = null;

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $from = $this->from ? now()->parse($this->from)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $this->to ? now()->parse($this->to)->endOfDay() : now()->endOfDay();
        $metrics = app(ReportService::class)->computeMetrics($companyId, $from, $to);

        return ['metrics' => $metrics];
    }
}
