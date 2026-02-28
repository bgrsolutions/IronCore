<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductReorderSetting;
use App\Models\ReorderSuggestion;
use App\Models\SupplierProductCost;
use App\Models\SupplierStockSnapshot;
use App\Models\SupplierStockSnapshotItem;
use Illuminate\Support\Facades\DB;

final class ReorderSuggestionService
{
    public function generate(int $companyId, int $periodDays = 30, ?int $createdByUserId = null): ReorderSuggestion
    {
        $to = now();
        $from = now()->copy()->subDays($periodDays)->startOfDay();

        $suggestion = ReorderSuggestion::query()->create([
            'company_id' => $companyId,
            'generated_at' => now(),
            'period_days' => $periodDays,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'payload' => [],
            'created_by_user_id' => $createdByUserId,
            'created_at' => now(),
        ]);

        $salesByProduct = $this->salesVelocityByProduct($companyId, $from, $to, $periodDays);
        $onHandByProduct = $this->onHandByProduct($companyId);
        $settingsByProduct = ProductReorderSetting::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('product_id');

        $rows = [];

        $products = Product::query()->where('product_type', 'stock')->where('is_active', true)->get();
        foreach ($products as $product) {
            $setting = $settingsByProduct->get($product->id);
            if ($setting && ! $setting->is_enabled) {
                continue;
            }

            $lead = (int) ($setting->lead_time_days ?? 3);
            $safety = (int) ($setting->safety_days ?? 7);
            $minCover = (int) ($setting->min_days_cover ?? 14);
            $maxCover = (int) ($setting->max_days_cover ?? 30);
            $targetDaysCover = max($minCover, min($maxCover, $minCover));
            $requiredWindowDays = $lead + $safety + $targetDaysCover;

            $avgDailySold = (float) ($salesByProduct[$product->id] ?? 0);
            $onHand = (float) ($onHandByProduct[$product->id] ?? 0);
            $targetQty = $avgDailySold * $requiredWindowDays;
            $suggested = max(0.0, $targetQty - max(0.0, $onHand));

            $negativeExposure = abs(min(0.0, $onHand));
            $supplierAvailable = null;
            $unitCost = null;
            $reason = 'Standard velocity-based reorder.';

            if ($negativeExposure > 0) {
                $reason = 'Urgent: negative on-hand exposure '.$negativeExposure;
            }

            if ($setting?->preferred_supplier_id) {
                $latestSnapshot = SupplierStockSnapshot::query()
                    ->where('company_id', $companyId)
                    ->where('supplier_id', $setting->preferred_supplier_id)
                    ->latest('snapshot_at')
                    ->first();

                if ($latestSnapshot) {
                    $snapItem = SupplierStockSnapshotItem::query()
                        ->where('supplier_stock_snapshot_id', $latestSnapshot->id)
                        ->where('product_id', $product->id)
                        ->first();
                    if ($snapItem) {
                        $supplierAvailable = (float) $snapItem->qty_available;
                        $unitCost = $snapItem->unit_cost !== null ? (float) $snapItem->unit_cost : null;
                    }
                }
            }

            $supplierCost = SupplierProductCost::query()
                ->where('company_id', $companyId)
                ->where('product_id', $product->id)
                ->when($setting?->preferred_supplier_id, fn ($q, $sid) => $q->where('supplier_id', $sid))
                ->latest('last_seen_at')
                ->first();
            if ($supplierCost) {
                $unitCost = (float) $supplierCost->last_unit_cost;
            }

            if ($setting?->min_order_qty !== null) {
                $suggested = max($suggested, (float) $setting->min_order_qty);
            }

            if ($setting?->pack_size_qty !== null && (float) $setting->pack_size_qty > 0) {
                $pack = (float) $setting->pack_size_qty;
                $suggested = ceil($suggested / $pack) * $pack;
            }

            if ($suggested <= 0) {
                continue;
            }

            $estimatedSpend = $unitCost !== null ? round($suggested * $unitCost, 2) : null;

            $row = [
                'reorder_suggestion_id' => $suggestion->id,
                'product_id' => $product->id,
                'suggested_qty' => round($suggested, 3),
                'days_cover_target' => $targetDaysCover,
                'avg_daily_sold' => round($avgDailySold, 4),
                'on_hand' => round($onHand, 3),
                'supplier_available' => $supplierAvailable,
                'negative_exposure' => $negativeExposure > 0 ? round($negativeExposure, 3) : null,
                'last_supplier_unit_cost' => $unitCost,
                'estimated_spend' => $estimatedSpend,
                'reason' => substr($reason, 0, 255),
                'created_at' => now(),
            ];

            $rows[] = $row;
        }

        if ($rows !== []) {
            DB::table('reorder_suggestion_items')->insert($rows);
        }

        $payload = [
            'items_count' => count($rows),
            'total_estimated_spend' => round(array_sum(array_map(fn ($r) => (float) ($r['estimated_spend'] ?? 0), $rows)), 2),
            'top_urgent_items' => collect($rows)->sortByDesc('negative_exposure')->take(10)->values()->all(),
        ];

        $suggestion->update(['payload' => $payload]);

        return $suggestion->fresh(['items.product']);
    }

    /** @return array<int,float> */
    private function salesVelocityByProduct(int $companyId, \Carbon\Carbon $from, \Carbon\Carbon $to, int $periodDays): array
    {
        $salesRows = DB::table('sales_document_lines')
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->join('products', 'products.id', '=', 'sales_document_lines.product_id')
            ->where('sales_documents.company_id', $companyId)
            ->where('sales_documents.status', 'posted')
            ->whereIn('sales_documents.doc_type', ['ticket', 'invoice', 'credit_note'])
            ->whereBetween('sales_documents.issue_date', [$from, $to])
            ->where('products.product_type', 'stock')
            ->selectRaw("sales_document_lines.product_id, SUM(CASE WHEN sales_documents.doc_type = 'credit_note' THEN -ABS(sales_document_lines.qty) ELSE ABS(sales_document_lines.qty) END) as qty_sold")
            ->groupBy('sales_document_lines.product_id')
            ->get();

        $out = [];
        foreach ($salesRows as $row) {
            $out[(int) $row->product_id] = max(0, ((float) $row->qty_sold) / max(1, $periodDays));
        }

        return $out;
    }

    /** @return array<int,float> */
    private function onHandByProduct(int $companyId): array
    {
        $rows = DB::table('stock_moves')
            ->where('company_id', $companyId)
            ->selectRaw('product_id, SUM(qty) as on_hand')
            ->groupBy('product_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->product_id] = (float) $row->on_hand;
        }

        return $out;
    }
}
