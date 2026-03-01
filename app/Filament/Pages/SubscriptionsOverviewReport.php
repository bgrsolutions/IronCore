<?php

namespace App\Filament\Pages;

use App\Models\Subscription;
use App\Models\SubscriptionRun;
use App\Support\Company\CompanyContext;
use Filament\Pages\Page;

class SubscriptionsOverviewReport extends Page
{
    protected static ?string $navigationGroup = 'Reporting';

    protected static ?string $navigationLabel = 'Subscriptions Overview';

    protected static ?string $slug = 'reports/subscriptions-overview';

    protected static string $view = 'filament.pages.subscriptions-overview-report';

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $active = Subscription::query()->where('company_id', $companyId)->where('status', 'active')->get();

        $mrr = 0.0;
        foreach ($active as $sub) {
            $interval = (int) ($sub->plan?->interval_months ?? 1);
            $price = (float) ($sub->price_net ?? $sub->plan?->price_net ?? 0);
            if ($interval > 0) {
                $mrr += $price / $interval;
            }
        }

        return [
            'activeCount' => $active->count(),
            'mrrEstimate' => round($mrr, 2),
            'dueSoon' => Subscription::query()->where('company_id', $companyId)->where('status', 'active')->whereBetween('next_run_at', [now(), now()->addDays(30)])->count(),
            'failedRuns' => SubscriptionRun::query()->where('company_id', $companyId)->where('status', 'failed')->where('run_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
