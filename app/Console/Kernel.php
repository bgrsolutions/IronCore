<?php

namespace App\Console;

use App\Console\Commands\RunDueSubscriptionsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        RunDueSubscriptionsCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('subscriptions:run-due')->hourly();
    }
}
