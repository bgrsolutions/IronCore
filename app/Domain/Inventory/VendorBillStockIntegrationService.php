<?php

namespace App\Domain\Inventory;

use App\Models\Location;
use App\Models\StockMove;
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

            $this->stockService->postMove([
                'company_id' => $bill->company_id,
                'product_id' => $line->product_id,
                'warehouse_id' => $warehouse->id,
                'location_id' => $location->id,
                'move_type' => 'receipt',
                'qty' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_price,
                'reference_type' => 'vendor_bill_line',
                'reference_id' => $line->id,
                'note' => 'Received from vendor bill #'.$bill->id,
                'occurred_at' => now(),
            ]);
        }
    }
}
