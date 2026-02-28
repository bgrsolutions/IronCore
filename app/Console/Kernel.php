<?php

namespace App\Console;

use App\Console\Commands\RunDueSubscriptionsCommand;
use App\Console\Commands\GenerateWeeklyReportSnapshotCommand;
use App\Console\Commands\GenerateDailyReportSnapshotCommand;
use App\Console\Commands\GenerateKpiDailyCommand;
use App\Console\Commands\GenerateKpiWeeklyCommand;
use App\Console\Commands\VerifactuExportCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        RunDueSubscriptionsCommand::class,
        GenerateDailyReportSnapshotCommand::class,
        GenerateWeeklyReportSnapshotCommand::class,
        VerifactuExportCommand::class,
        GenerateKpiDailyCommand::class,
        GenerateKpiWeeklyCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('subscriptions:run-due')->hourly();
        $schedule->command('reports:snapshot-daily')->dailyAt('02:00');
        $schedule->command('reports:snapshot-weekly')->sundays()->at('03:00');
        $schedule->command('reports:kpi-daily')->dailyAt('02:15');
        $schedule->command('reports:kpi-weekly')->sundays()->at('03:15');
    }
}
