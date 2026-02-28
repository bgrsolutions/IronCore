<?php

namespace App\Domain\Inventory;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\StockMove;
use App\Models\SupplierProductCost;
use App\Models\VendorBill;
use App\Models\Warehouse;

final class VendorBillStockIntegrationService
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function receiveForPostedBill(VendorBill $bill): void
    {
        if ($bill->status !== 'posted') {
            return;
        }

        $warehouse = Warehouse::query()->firstOrCreate(
            ['company_id' => $bill->company_id, 'is_default' => true],
            ['name' => 'Main Warehouse', 'code' => 'MAIN', 'is_default' => true]
        );

        $location = Location::query()->firstOrCreate(
            ['company_id' => $bill->company_id, 'warehouse_id' => $warehouse->id, 'is_default' => true],
            ['name' => 'Default', 'code' => 'DEF', 'is_default' => true]
        );

        foreach ($bill->lines()->where('is_stock_item', true)->whereNotNull('product_id')->get() as $line) {
            $alreadyReceived = StockMove::query()
                ->where('reference_type', 'vendor_bill_line')
                ->where('reference_id', $line->id)
                ->exists();

            if ($alreadyReceived) {
                continue;
            }

            $newUnitCost = (float) $line->unit_price;
            $previousCost = SupplierProductCost::query()
                ->where('company_id', $bill->company_id)
                ->where('supplier_id', $bill->supplier_id)
                ->where('product_id', $line->product_id)
                ->first();

            $increaseFlag = false;
            $increasePercent = null;

            if ($previousCost && (float) $previousCost->last_unit_cost > 0) {
                $oldUnitCost = (float) $previousCost->last_unit_cost;
                $increasePercent = round((($newUnitCost - $oldUnitCost) / $oldUnitCost) * 100, 2);
                if ($newUnitCost > ($oldUnitCost * 1.05)) {
                    $increaseFlag = true;

                    AuditLog::query()->create([
                        'company_id' => $bill->company_id,
                        'user_id' => auth()->id(),
                        'action' => 'supplier_cost_increase',
                        'auditable_type' => 'vendor_bill',
                        'auditable_id' => $bill->id,
                        'payload' => [
                            'event_type' => 'supplier_cost_increase',
                            'supplier_id' => (int) $bill->supplier_id,
                            'product_id' => (int) $line->product_id,
                            'old_unit_cost' => $oldUnitCost,
                            'new_unit_cost' => $newUnitCost,
                            'percent_change' => $increasePercent,
                            'bill_id' => (int) $bill->id,
                        ],
                        'created_at' => now(),
                    ]);
                }
            }

            $line->update([
                'cost_increase_flag' => $increaseFlag,
                'cost_increase_percent' => $increasePercent,
            ]);

            SupplierProductCost::query()->updateOrCreate(
                [
                    'company_id' => $bill->company_id,
                    'supplier_id' => $bill->supplier_id,
                    'product_id' => $line->product_id,
                ],
                [
                    'last_unit_cost' => $newUnitCost,
                    'currency' => $bill->currency ?? 'EUR',
                    'last_seen_at' => now(),
                ]
            );

            $this->stockService->postMove([
                'company_id' => $bill->company_id,
                'product_id' => $line->product_id,
                'warehouse_id' => $warehouse->id,
                'location_id' => $location->id,
                'move_type' => 'receipt',
                'qty' => (float) $line->quantity,
                'unit_cost' => $newUnitCost,
                'reference_type' => 'vendor_bill_line',
                'reference_id' => $line->id,
                'note' => 'Received from vendor bill #'.$bill->id,
                'occurred_at' => now(),
            ]);
        }
    }
}
