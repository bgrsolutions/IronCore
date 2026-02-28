<?php

namespace App\Console\Commands;

use App\Domain\Billing\SubscriptionBillingService;
use Illuminate\Console\Command;

class RunDueSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:run-due {--company_id=}';

    protected $description = 'Run due subscriptions and generate recurring sales documents';

    public function handle(SubscriptionBillingService $billingService): int
    {
        $companyId = $this->option('company_id');
        $billingService->runDueSubscriptions($companyId ? (int) $companyId : null);
        $this->info('Due subscriptions processed.');

        return self::SUCCESS;
    }
}
