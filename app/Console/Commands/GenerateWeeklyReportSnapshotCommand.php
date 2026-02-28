<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateWeeklyReportSnapshotCommand extends Command
{
    protected $signature = 'reports:snapshot-weekly {--week-start=} {--company=}';

    protected $description = 'Generate weekly report snapshots.';

    public function handle(ReportService $service): int
    {
        $weekStart = Carbon::parse((string) ($this->option('week-start') ?: now()->startOfWeek()->toDateString()));
        $companyId = $this->option('company') ? (int) $this->option('company') : null;

        foreach ($service->targetCompanies($companyId) as $company) {
            $service->generateWeeklySnapshot($company->id, $weekStart);
        }

        $this->info('Weekly report snapshots generated.');

        return self::SUCCESS;
    }
}
