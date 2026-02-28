<?php

namespace App\Services;

use App\Models\PurchasePlan;
use App\Models\PurchasePlanItem;
use App\Models\ReorderSuggestionItem;
use App\Models\VendorBill;
use Illuminate\Support\Facades\DB;

class PurchasePlanService
{
    /** @param array<int,int> $reorderSuggestionItemIds */
    public function createFromSuggestionItems(int $companyId, array $reorderSuggestionItemIds, ?int $supplierId = null, ?int $storeLocationId = null, ?int $userId = null): PurchasePlan
    {
        return DB::transaction(function () use ($companyId, $reorderSuggestionItemIds, $supplierId, $storeLocationId, $userId): PurchasePlan {
            $plan = PurchasePlan::query()->create([
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
                'store_location_id' => $storeLocationId,
                'status' => 'draft',
                'planned_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $items = ReorderSuggestionItem::query()->whereIn('id', $reorderSuggestionItemIds)->get();
            foreach ($items as $item) {
                PurchasePlanItem::query()->create([
                    'purchase_plan_id' => $plan->id,
                    'product_id' => $item->product_id,
                    'suggested_qty' => $item->suggested_qty,
                    'ordered_qty' => $item->suggested_qty,
                    'received_qty' => 0,
                    'unit_cost_estimate' => $item->last_supplier_unit_cost,
                    'currency' => 'EUR',
                    'source_reorder_suggestion_item_id' => $item->id,
                    'status' => 'planned',
                ]);
            }

            return $plan->fresh(['items']);
        });
    }

    public function markOrdered(PurchasePlan $plan, ?string $expectedAt = null): PurchasePlan
    {
        $plan->update([
            'status' => 'ordered',
            'ordered_at' => now(),
            'expected_at' => $expectedAt ?: $plan->expected_at,
        ]);
        $plan->items()->where('status', 'planned')->update(['status' => 'ordered']);

        return $plan->fresh(['items']);
    }

    public function receiveItem(PurchasePlan $plan, int $itemId, float $receivedQty): PurchasePlan
    {
        $item = $plan->items()->findOrFail($itemId);
        $newReceived = (float) $item->received_qty + max(0, $receivedQty);
        $ordered = (float) ($item->ordered_qty ?? $item->suggested_qty);

        $item->update([
            'received_qty' => $newReceived,
            'status' => $newReceived >= $ordered ? 'received' : 'ordered',
        ]);

        $this->refreshPlanStatus($plan);

        return $plan->fresh(['items']);
    }

    public function syncReceivedFromVendorBill(VendorBill $vendorBill): void
    {
        if (! $vendorBill->purchase_plan_id) {
            return;
        }

        $plan = PurchasePlan::query()->with('items')->find($vendorBill->purchase_plan_id);
        if (! $plan) {
            return;
        }

        foreach ($vendorBill->lines as $line) {
            if (! $line->product_id) {
                continue;
            }
            $item = $plan->items->firstWhere('product_id', $line->product_id);
            if (! $item) {
                continue;
            }
            $ordered = (float) ($item->ordered_qty ?? $item->suggested_qty);
            $newReceived = (float) $item->received_qty + abs((float) $line->quantity);
            $item->update([
                'received_qty' => $newReceived,
                'status' => $newReceived >= $ordered ? 'received' : 'ordered',
            ]);
        }

        $this->refreshPlanStatus($plan->fresh(['items']));
    }

    private function refreshPlanStatus(PurchasePlan $plan): void
    {
        $items = $plan->items;
        if ($items->count() === 0) {
            return;
        }

        $allReceived = $items->every(fn (PurchasePlanItem $item): bool => $item->status === 'received');
        $anyReceived = $items->contains(fn (PurchasePlanItem $item): bool => (float) $item->received_qty > 0);

        $status = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : ($plan->status === 'draft' ? 'draft' : 'ordered'));
        $plan->update(['status' => $status]);
    }
}
