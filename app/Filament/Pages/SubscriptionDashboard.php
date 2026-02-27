<?php

namespace App\Filament\Pages;

use App\Models\Subscription;
use App\Models\SubscriptionRun;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class SubscriptionDashboard extends Page
{
    protected static ?string $navigationLabel = 'Subscription Dashboard';

    protected static ?string $slug = 'subscription-dashboard';

    protected static string $view = 'filament.pages.subscription-dashboard';

    protected function getViewData(): array
    {
        $companyId = CompanyContext::get();

        $dueToday = Subscription::query()->whereDate('next_run_at', now()->toDateString())->where('status', 'active')->count();
        $failed7d = SubscriptionRun::query()->where('status', 'failed')->where('run_at', '>=', now()->subDays(7))->count();
        $upcoming = Subscription::query()->where('status', 'active')->whereBetween('next_run_at', [now(), now()->addDays(14)])->count();

        return compact('dueToday', 'failed7d', 'upcoming');
    }
}
