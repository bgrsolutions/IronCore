<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyReportSnapshotCommand extends Command
{
    protected $signature = 'reports:snapshot-daily {--date=} {--company=}';

    protected $description = 'Generate daily report snapshots.';

    public function handle(ReportService $service): int
    {
        $date = Carbon::parse((string) ($this->option('date') ?: now()->toDateString()));
        $companyId = $this->option('company') ? (int) $this->option('company') : null;

        foreach ($service->targetCompanies($companyId) as $company) {
            $service->generateDailySnapshot($company->id, $date);
        }

        $this->info('Daily report snapshots generated.');

        return self::SUCCESS;
    }
}
