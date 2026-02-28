<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ProductCost;
use App\Models\Repair;
use App\Models\ReportSnapshot;
use App\Models\SalesDocument;
use App\Models\StockMove;
use App\Models\Subscription;
use App\Models\SubscriptionRun;
use App\Models\VendorBill;
use App\Services\RepairMetricsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    /** @return array<string,mixed> */
    public function computeMetrics(int $companyId, Carbon $from, Carbon $to): array
    {
        $docs = SalesDocument::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween('issue_date', [$from, $to])
            ->get();

        $salesDocs = $docs->whereIn('doc_type', ['ticket', 'invoice']);
        $creditDocs = $docs->where('doc_type', 'credit_note');

        $revenueGross = (float) $salesDocs->sum('gross_total') - (float) $creditDocs->sum('gross_total');
        $revenueNet = (float) $salesDocs->sum('net_total') - (float) $creditDocs->sum('net_total');
        $taxTotal = (float) $salesDocs->sum('tax_total') - (float) $creditDocs->sum('tax_total');

        // COGS sign convention: line cost_total is stored as absolute positive cost.
        // Credit notes net COGS by subtracting credit-note COGS from sales COGS.
        $salesCogs = (float) $salesDocs->sum(fn (SalesDocument $doc) => $this->documentCogsAbsolute($doc));
        $creditCogs = (float) $creditDocs->sum(fn (SalesDocument $doc) => $this->documentCogsAbsolute($doc));
        $cogsTotal = $salesCogs - $creditCogs;

        $grossProfit = $revenueNet - $cogsTotal;
        $grossMarginPercent = $revenueNet != 0.0 ? round(($grossProfit / $revenueNet) * 100, 2) : 0.0;

        $repairs = Repair::query()->where('company_id', $companyId)->get();
        $repairsInvoiced = $repairs->filter(fn (Repair $r) => $r->linkedSalesDocument && $r->linkedSalesDocument->status === 'posted');
        $repairLines = DB::table('sales_document_lines')
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->join('repairs', 'repairs.linked_sales_document_id', '=', 'sales_documents.id')
            ->where('repairs.company_id', $companyId)
            ->select('sales_document_lines.*')
            ->get();

        $repairLabourNet = (float) $repairLines->filter(function ($line): bool {
            $description = strtolower((string) ($line->description ?? ''));

            return str_contains($description, 'labour') || str_contains($description, 'mano de obra') || str_contains($description, 'diagnostic');
        })->sum('line_net');
        $repairPartsCogs = (float) DB::table('repair_parts')->where('company_id', $companyId)->sum('line_cost');

        $unbilledTimeMinutes = (int) DB::table('repair_time_entries')
            ->join('repairs', 'repairs.id', '=', 'repair_time_entries.repair_id')
            ->where('repairs.company_id', $companyId)
            ->whereNull('repairs.linked_sales_document_id')
            ->sum('repair_time_entries.minutes');
        $billedTimeMinutes = (int) DB::table('repair_time_entries')
            ->join('repairs', 'repairs.id', '=', 'repair_time_entries.repair_id')
            ->where('repairs.company_id', $companyId)
            ->whereNotNull('repairs.linked_sales_document_id')
            ->sum('repair_time_entries.minutes');

        $billedTimeRatio = ($billedTimeMinutes + $unbilledTimeMinutes) > 0
            ? round($billedTimeMinutes / ($billedTimeMinutes + $unbilledTimeMinutes), 4)
            : 0.0;

        $productCosts = ProductCost::query()->where('company_id', $companyId)->get()->keyBy('product_id');
        $onHandByProduct = StockMove::query()
            ->where('company_id', $companyId)
            ->selectRaw('product_id, SUM(qty) as on_hand')
            ->groupBy('product_id')
            ->get();

        $stockValue = 0.0;
        $negativeStockCount = 0;
        $negativeExposure = 0.0;
        foreach ($onHandByProduct as $row) {
            $avg = (float) optional($productCosts->get($row->product_id))->avg_cost;
            $onHand = (float) $row->on_hand;
            $stockValue += max(0, $onHand) * $avg;
            if ($onHand < 0) {
                $negativeStockCount++;
                $negativeExposure += abs($onHand) * $avg;
            }
        }

        $lastMoves = StockMove::query()
            ->where('company_id', $companyId)
            ->selectRaw('product_id, MAX(occurred_at) as last_moved_at')
            ->groupBy('product_id')
            ->get();

        $dead60 = 0;
        $dead120 = 0;
        $deadRows = [];
        foreach ($lastMoves as $row) {
            $last = Carbon::parse($row->last_moved_at);
            $days = $last->diffInDays(now());
            $onHand = (float) optional($onHandByProduct->firstWhere('product_id', $row->product_id))->on_hand;
            $avg = (float) optional($productCosts->get($row->product_id))->avg_cost;
            $value = max(0, $onHand) * $avg;

            if ($days >= 60) {
                $dead60++;
            }
            if ($days >= 120) {
                $dead120++;
            }
            if ($days >= 60 && $value > 0) {
                $deadRows[] = [
                    'product_id' => (int) $row->product_id,
                    'days_without_move' => $days,
                    'value' => round($value, 2),
                ];
            }
        }
        usort($deadRows, fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        $activeSubs = Subscription::query()->where('company_id', $companyId)->where('status', 'active')->get();
        $mrr = 0.0;
        foreach ($activeSubs as $sub) {
            $interval = (int) ($sub->plan?->interval_months ?? 1);
            $priceNet = (float) ($sub->price_net ?? $sub->plan?->price_net ?? 0);
            if ($interval > 0) {
                $mrr += $priceNet / $interval;
            }
        }

        $renewals7 = Subscription::query()->where('company_id', $companyId)->where('status', 'active')->whereBetween('next_run_at', [now(), now()->copy()->addDays(7)])->count();
        $renewals30 = Subscription::query()->where('company_id', $companyId)->where('status', 'active')->whereBetween('next_run_at', [now(), now()->copy()->addDays(30)])->count();
        $failedRuns7 = SubscriptionRun::query()->where('company_id', $companyId)->where('status', 'failed')->where('run_at', '>=', now()->copy()->subDays(7))->count();

        $postedBillsDue = VendorBill::query()->where('company_id', $companyId)->where('status', 'posted')->whereNull('cancelled_at')->whereDate('due_date', '<=', now())->count();
        $billsDue7 = VendorBill::query()->where('company_id', $companyId)->where('status', 'posted')->whereNull('cancelled_at')->whereBetween('due_date', [now()->toDateString(), now()->copy()->addDays(7)->toDateString()])->count();
        $billsDue30 = VendorBill::query()->where('company_id', $companyId)->where('status', 'posted')->whereNull('cancelled_at')->whereBetween('due_date', [now()->toDateString(), now()->copy()->addDays(30)->toDateString()])->count();

        $negativeMarginDocs = $salesDocs->map(function (SalesDocument $doc): array {
            $cogs = $this->documentCogsSigned($doc);
            $margin = (float) $doc->net_total - $cogs;

            return ['id' => $doc->id, 'full_number' => $doc->full_number, 'revenue_net' => (float) $doc->net_total, 'cogs' => $cogs, 'margin' => $margin];
        })->filter(fn (array $row): bool => $row['margin'] < 0)->values()->all();

        $topProfitProducts = $this->topProducts($salesDocs, 'profit');
        $topRevenueProducts = $this->topProducts($salesDocs, 'revenue');

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'sales' => [
                'revenue_gross' => round($revenueGross, 2),
                'revenue_net' => round($revenueNet, 2),
                'tax_total' => round($taxTotal, 2),
                'cogs_total' => round($cogsTotal, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_margin_percent' => $grossMarginPercent,
                'negative_margin_documents' => $negativeMarginDocs,
                'top_products_by_gross_profit' => array_slice($topProfitProducts, 0, 20),
                'top_products_by_revenue' => array_slice($topRevenueProducts, 0, 20),
                'below_cost_sales_last_7_days' => $this->belowCostSalesLastDays($companyId, 7),
            ],
            'repairs' => [
                'repairs_count' => $repairs->count(),
                'repairs_invoiced_count' => $repairsInvoiced->count(),
                'repairs_total_billed_net' => round((float) $repairsInvoiced->sum(fn (Repair $r) => (float) optional($r->linkedSalesDocument)->net_total), 2),
                'repairs_total_billed_gross' => round((float) $repairsInvoiced->sum(fn (Repair $r) => (float) optional($r->linkedSalesDocument)->gross_total), 2),
                'repair_labour_net' => round($repairLabourNet, 2),
                'repair_parts_cogs' => round($repairPartsCogs, 2),
                'unbilled_time_minutes' => $unbilledTimeMinutes,
                'billed_time_vs_logged_ratio' => $billedTimeRatio,
            ],
            'inventory' => [
                'stock_value' => round($stockValue, 2),
                'negative_stock_count' => $negativeStockCount,
                'negative_stock_value_exposure' => round($negativeExposure, 2),
                'products_not_moved_60d' => $dead60,
                'products_not_moved_120d' => $dead120,
                'top_dead_stock_by_value' => array_slice($deadRows, 0, 20),
            ],
            'subscriptions' => [
                'active_subscriptions_count' => $activeSubs->count(),
                'mrr_estimate' => round($mrr, 2),
                'upcoming_renewals_7d' => $renewals7,
                'upcoming_renewals_30d' => $renewals30,
                'failed_runs_7d' => $failedRuns7,
            ],
            'cash_discipline' => [
                'unpaid_vendor_bills_count' => $postedBillsDue,
                'bills_due_7d' => $billsDue7,
                'bills_due_30d' => $billsDue30,
            ],
        ];
    }

    public function generateDailySnapshot(int $companyId, Carbon $date): ReportSnapshot
    {
        $metrics = $this->computeMetrics($companyId, $date->copy()->startOfDay(), $date->copy()->endOfDay());

        $existing = ReportSnapshot::query()
            ->where('company_id', $companyId)
            ->where('snapshot_type', 'daily')
            ->whereDate('snapshot_date', $date->toDateString())
            ->first();

        if ($existing) {
            $existing->update(['payload' => $metrics, 'generated_at' => now(), 'generated_by_user_id' => auth()->id()]);
            return $existing->fresh();
        }

        return ReportSnapshot::query()->create([
            'company_id' => $companyId,
            'snapshot_type' => 'daily',
            'snapshot_date' => $date->toDateString(),
            'week_start_date' => null,
            'payload' => $metrics,
            'generated_at' => now(),
            'generated_by_user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function generateWeeklySnapshot(int $companyId, Carbon $weekStartDate): ReportSnapshot
    {
        $start = $weekStartDate->copy()->startOfDay();
        $end = $weekStartDate->copy()->addDays(6)->endOfDay();
        $metrics = $this->computeMetrics($companyId, $start, $end);

        $existing = ReportSnapshot::query()
            ->where('company_id', $companyId)
            ->where('snapshot_type', 'weekly')
            ->whereDate('week_start_date', $weekStartDate->toDateString())
            ->first();

        if ($existing) {
            $existing->update(['payload' => $metrics, 'generated_at' => now(), 'generated_by_user_id' => auth()->id()]);
            return $existing->fresh();
        }

        return ReportSnapshot::query()->create([
            'company_id' => $companyId,
            'snapshot_type' => 'weekly',
            'snapshot_date' => null,
            'week_start_date' => $weekStartDate->toDateString(),
            'payload' => $metrics,
            'generated_at' => now(),
            'generated_by_user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function topProducts(Collection $salesDocs, string $metric): array
    {
        $bucket = [];
        foreach ($salesDocs as $doc) {
            foreach ($doc->lines as $line) {
                $key = (string) ($line->product_id ?? 'line-'.$line->id);
                if (! isset($bucket[$key])) {
                    $bucket[$key] = [
                        'product_id' => $line->product_id,
                        'description' => $line->description,
                        'revenue_net' => 0.0,
                        'cogs' => 0.0,
                        'gross_profit' => 0.0,
                    ];
                }
                $bucket[$key]['revenue_net'] += (float) $line->line_net;
                $bucket[$key]['cogs'] += (float) ($line->cost_total ?? 0);
                $bucket[$key]['gross_profit'] = $bucket[$key]['revenue_net'] - $bucket[$key]['cogs'];
            }
        }

        $rows = array_values($bucket);
        usort($rows, function (array $a, array $b) use ($metric): int {
            return $metric === 'revenue'
                ? ($b['revenue_net'] <=> $a['revenue_net'])
                : ($b['gross_profit'] <=> $a['gross_profit']);
        });

        return array_map(function (array $row): array {
            $row['revenue_net'] = round((float) $row['revenue_net'], 2);
            $row['cogs'] = round((float) $row['cogs'], 2);
            $row['gross_profit'] = round((float) $row['gross_profit'], 2);

            return $row;
        }, $rows);
    }



    /** @return array<int,array<string,mixed>> */
    public function salesMarginRows(int $companyId, Carbon $from, Carbon $to): array
    {
        $docs = SalesDocument::query()->with('lines')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereIn('doc_type', ['ticket', 'invoice', 'credit_note'])
            ->whereBetween('issue_date', [$from, $to])
            ->get();

        return $docs->map(function (SalesDocument $doc): array {
            $cogs = $this->documentCogsSigned($doc);
            return [
                'sales_document_id' => $doc->id,
                'full_number' => $doc->full_number,
                'doc_type' => $doc->doc_type,
                'net_total' => (float) $doc->net_total,
                'gross_total' => (float) $doc->gross_total,
                'cogs_total' => $cogs,
                'margin' => round((float) $doc->net_total - $cogs, 2),
            ];
        })->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function deadStockRows(int $companyId): array
    {
        $productCosts = ProductCost::query()->where('company_id', $companyId)->get()->keyBy('product_id');
        $onHandByProduct = StockMove::query()
            ->where('company_id', $companyId)
            ->selectRaw('product_id, SUM(qty) as on_hand_qty')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        return StockMove::query()
            ->where('company_id', $companyId)
            ->selectRaw('product_id, MAX(occurred_at) as last_moved_at')
            ->groupBy('product_id')
            ->get()
            ->map(function ($row) use ($onHandByProduct, $productCosts): array {
                $onHand = (float) optional($onHandByProduct->get($row->product_id))->on_hand_qty;
                $avg = (float) optional($productCosts->get($row->product_id))->avg_cost;
                $lastMoved = Carbon::parse($row->last_moved_at);

                return [
                    'product_id' => (int) $row->product_id,
                    'days_without_move' => $lastMoved->diffInDays(now()),
                    'value' => round(max(0, $onHand) * $avg, 2),
                    'last_moved_at' => $lastMoved->toDateString(),
                    'on_hand_qty' => round($onHand, 4),
                ];
            })
            ->filter(fn (array $row): bool => $row['days_without_move'] >= 60 && $row['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function negativeStockRows(int $companyId): array
    {
        $costs = ProductCost::query()->where('company_id', $companyId)->get()->keyBy('product_id');
        return StockMove::query()->where('company_id', $companyId)
            ->selectRaw('product_id, warehouse_id, SUM(qty) as on_hand')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->filter(fn ($r) => (float) $r->on_hand < 0)
            ->map(function ($r) use ($costs): array {
                $avg = (float) optional($costs->get($r->product_id))->avg_cost;
                return [
                    'product_id' => (int) $r->product_id,
                    'warehouse_id' => (int) $r->warehouse_id,
                    'on_hand' => (float) $r->on_hand,
                    'exposure' => round(abs((float) $r->on_hand) * $avg, 2),
                ];
            })->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function repairProfitabilityRows(int $companyId): array
    {
        $threshold = (int) config('repairs.time_leak_threshold_minutes', 15);
        $metricsService = app(RepairMetricsService::class);

        return Repair::query()->where('company_id', $companyId)->with('linkedSalesDocument')->get()->map(function (Repair $repair) use ($metricsService, $threshold): array {
            $logged = $metricsService->loggedMinutes($repair);
            $partsCost = (float) DB::table('repair_parts')->where('repair_id', $repair->id)->sum('line_cost');
            $billed = (float) optional($repair->linkedSalesDocument)->net_total;
            $hasLabour = $metricsService->hasLabourLines($repair);

            return [
                'repair_id' => $repair->id,
                'logged_minutes' => $logged,
                'billed_net' => round($billed, 2),
                'parts_cost' => round($partsCost, 2),
                'gross_profit_estimate' => round($billed - $partsCost, 2),
                'time_leak_flag' => $logged > $threshold && ! $hasLabour,
            ];
        })->all();
    }

    /**
     * cost_total is stored as absolute (positive) line cost.
     * Credit notes are netted by applying a -1 sign at document level.
     */
    private function documentCogsSigned(SalesDocument $doc): float
    {
        $sign = $doc->doc_type === 'credit_note' ? -1 : 1;

        return round($sign * $this->documentCogsAbsolute($doc), 2);
    }

    private function documentCogsAbsolute(SalesDocument $doc): float
    {
        return (float) $doc->lines->sum(fn ($l) => abs((float) ($l->cost_total ?? 0)));
    }

    /** @return array{count:int,documents:array<int,array<string,mixed>>} */
    private function belowCostSalesLastDays(int $companyId, int $days): array
    {
        $from = now()->copy()->subDays($days)->startOfDay();
        $docs = SalesDocument::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereIn('doc_type', ['ticket', 'invoice'])
            ->whereBetween('issue_date', [$from, now()])
            ->get();

        $rows = $docs->map(function (SalesDocument $doc): array {
            $cogs = $this->documentCogsSigned($doc);
            $margin = (float) $doc->net_total - $cogs;

            return [
                'id' => (int) $doc->id,
                'full_number' => (string) $doc->full_number,
                'issue_date' => optional($doc->issue_date)->toDateString(),
                'revenue_net' => round((float) $doc->net_total, 2),
                'cogs' => round($cogs, 2),
                'margin' => round($margin, 2),
            ];
        })->filter(fn (array $row): bool => $row['margin'] < 0)->values()->all();

        return ['count' => count($rows), 'documents' => $rows];
    }

    /** @return Collection<int,Company> */
    public function targetCompanies(?int $companyId = null): Collection
    {
        return $companyId
            ? Company::query()->whereKey($companyId)->get()
            : Company::query()->get();
    }
}
