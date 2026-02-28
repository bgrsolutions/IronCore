<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateKpiDailyCommand extends Command
{
    protected $signature = 'reports:kpi-daily {--date=} {--company=}';

    protected $description = 'Generate daily KPI snapshots.';

    public function handle(ReportService $service): int
    {
        $date = Carbon::parse((string) ($this->option('date') ?: now()->toDateString()));
        $companyId = $this->option('company') ? (int) $this->option('company') : null;

        foreach ($service->targetCompanies($companyId) as $company) {
            $service->generateDailySnapshot($company->id, $date);
        }

        $this->info('Daily KPI snapshots generated.');

        return self::SUCCESS;
    }
}
