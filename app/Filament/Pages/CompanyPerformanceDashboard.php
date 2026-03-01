<?php

namespace App\Filament\Pages;

use App\Models\ReportSnapshot;
use App\Services\ReportService;
use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Filament\Pages\Page;

class CompanyPerformanceDashboard extends Page
{
    protected static ?string $navigationGroup = 'Reporting';

    protected static ?string $navigationLabel = 'Company Performance';

    protected static ?string $slug = 'reports/company-performance';

    protected static string $view = 'filament.pages.company-performance-dashboard';

    public ?string $from = null;

    public ?string $to = null;

    public function mount(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $snapshot = ReportSnapshot::query()
            ->where('company_id', $companyId)
            ->where('snapshot_type', 'daily')
            ->whereDate('snapshot_date', $this->to)
            ->latest('generated_at')
            ->first();

        $metrics = $snapshot?->payload ?? app(ReportService::class)->computeMetrics(
            $companyId,
            Carbon::parse((string) $this->from)->startOfDay(),
            Carbon::parse((string) $this->to)->endOfDay()
        );

        return ['metrics' => $metrics, 'usingSnapshot' => $snapshot !== null];
    }

    public function recalculateLive(): void
    {
        // noop; Livewire rerender uses current from/to and bypasses snapshot if no snapshot for date
    }
}
