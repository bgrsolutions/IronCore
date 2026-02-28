<?php

namespace App\Domain\Billing;

use App\Services\SalesDocumentService;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Models\SubscriptionRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SubscriptionBillingService
{
    public function __construct(private readonly SalesDocumentService $salesDocumentService)
    {
    }

    public function computeNextRun(Subscription $subscription, ?Carbon $base = null): Carbon
    {
        $interval = (int) ($subscription->plan?->interval_months ?? 1);
        $anchor = $base ?? $subscription->next_run_at;

        return $anchor->copy()->addMonthsNoOverflow($interval);
    }

    public function generateInvoiceForSubscription(Subscription $subscription, Carbon $runAt): ?int
    {
        return DB::transaction(function () use ($subscription, $runAt): ?int {
            $subscription->loadMissing('plan', 'items', 'customer');

            if ($subscription->status !== 'active') {
                $this->logRun($subscription, $runAt, 'skipped', 'Subscription is not active.');

                return null;
            }

            if ($subscription->ends_at && now()->greaterThan($subscription->ends_at)) {
                $subscription->update(['status' => 'cancelled', 'cancel_reason' => 'Subscription ended automatically.']);
                $this->logRun($subscription, $runAt, 'skipped', 'Subscription ended and was cancelled automatically.');

                return null;
            }

            $effective = $this->effectiveConfig($subscription);

            if ($effective['price_net'] === null || $effective['tax_rate'] === null) {
                throw new \RuntimeException('Subscription missing billing price/tax configuration.');
            }

            $doc = \App\Models\SalesDocument::query()->create([
                'company_id' => $subscription->company_id,
                'customer_id' => $subscription->customer_id,
                'doc_type' => $effective['doc_type'],
                'series' => $effective['series'],
                'status' => 'draft',
                'issue_date' => $runAt,
                'currency' => $effective['currency'],
                'source' => 'manual',
                'source_ref' => sprintf('subscription:%d:%s', $subscription->id, $runAt->toDateString()),
                'created_by_user_id' => $subscription->created_by_user_id,
            ]);

            if ($subscription->items()->exists()) {
                $lineNo = 1;
                foreach ($subscription->items as $item) {
                    $net = round((float) $item->qty * (float) $item->unit_price, 2);
                    $tax = round($net * ((float) $item->tax_rate / 100), 2);
                    $doc->lines()->create([
                        'line_no' => $lineNo++,
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'qty' => $item->qty,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => $item->tax_rate,
                        'line_net' => $net,
                        'line_tax' => $tax,
                        'line_gross' => round($net + $tax, 2),
                    ]);
                }
            } else {
                $name = $subscription->plan?->name ?? 'Recurring Service';
                $net = round((float) $effective['price_net'], 2);
                $tax = round($net * ((float) $effective['tax_rate'] / 100), 2);
                $doc->lines()->create([
                    'line_no' => 1,
                    'description' => $name,
                    'qty' => 1,
                    'unit_price' => $effective['price_net'],
                    'tax_rate' => $effective['tax_rate'],
                    'line_net' => $net,
                    'line_tax' => $tax,
                    'line_gross' => round($net + $tax, 2),
                ]);
            }

            if ((bool) $effective['auto_post']) {
                $this->salesDocumentService->post($doc);
            }

            AuditLog::query()->create([
                'company_id' => $subscription->company_id,
                'user_id' => auth()->id(),
                'action' => 'subscription.invoice_generated',
                'auditable_type' => 'subscription',
                'auditable_id' => $subscription->id,
                'payload' => ['sales_document_id' => $doc->id, 'status' => $doc->fresh()->status],
                'created_at' => now(),
            ]);

            $this->logRun($subscription, $runAt, 'success', 'Invoice generated.', $doc->id);

            return $doc->id;
        });
    }

    public function runDueSubscriptions(?int $companyId = null): void
    {
        $query = Subscription::query()->where('next_run_at', '<=', now());
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $query->orderBy('next_run_at')->chunkById(100, function ($subs): void {
            foreach ($subs as $subscription) {
                try {
                    $runAt = $subscription->next_run_at->copy();
                    $this->generateInvoiceForSubscription($subscription, $runAt);

                    if ($subscription->status === 'active') {
                        $subscription->update(['next_run_at' => $this->computeNextRun($subscription, $runAt)]);
                    }
                } catch (\Throwable $e) {
                    $this->logRun($subscription, now(), 'failed', 'Run failed.', null, $e);
                }
            }
        });
    }

    /** @return array<string,mixed> */
    private function effectiveConfig(Subscription $subscription): array
    {
        $plan = $subscription->plan;
        $settings = CompanySetting::query()->where('company_id', $subscription->company_id)->first();

        return [
            'price_net' => $subscription->price_net ?? $plan?->price_net,
            'tax_rate' => $subscription->tax_rate ?? $plan?->tax_rate,
            'currency' => (string) ($subscription->currency ?: $plan?->currency ?: 'EUR'),
            'doc_type' => (string) ($subscription->doc_type ?? $plan?->default_doc_type ?? 'invoice'),
            'series' => (string) ($subscription->series ?? $plan?->default_series ?? ($settings?->invoice_series_prefixes['F'] ?? 'F')),
            'auto_post' => (bool) ($subscription->auto_post ?? $plan?->auto_post ?? false),
        ];
    }

    private function logRun(Subscription $subscription, Carbon $runAt, string $status, ?string $message = null, ?int $docId = null, ?\Throwable $e = null): void
    {
        SubscriptionRun::query()->create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'run_at' => $runAt,
            'period_start' => $runAt,
            'period_end' => $this->computeNextRun($subscription, $runAt),
            'status' => $status,
            'message' => $message,
            'generated_sales_document_id' => $docId,
            'error_class' => $e ? get_class($e) : null,
            'error_message' => $e?->getMessage(),
            'created_at' => now(),
        ]);
    }
}
