<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateKpiWeeklyCommand extends Command
{
    protected $signature = 'reports:kpi-weekly {--week-start=} {--company=}';

    protected $description = 'Generate weekly KPI snapshots.';

    public function handle(ReportService $service): int
    {
        $weekStart = Carbon::parse((string) ($this->option('week-start') ?: now()->startOfWeek()->toDateString()));
        $companyId = $this->option('company') ? (int) $this->option('company') : null;

        foreach ($service->targetCompanies($companyId) as $company) {
            $service->generateWeeklySnapshot($company->id, $weekStart);
        }

        $this->info('Weekly KPI snapshots generated.');

        return self::SUCCESS;
    }
}
