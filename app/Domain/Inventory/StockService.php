<?php

namespace App\Domain\Inventory;

use App\Models\AuditLog;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductWarehouseStock;
use App\Models\StockMove;
use App\Support\Company\CompanyContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StockService
{
    private const INFLOW_TYPES = ['receipt', 'adjustment_in', 'transfer_in', 'return_in'];

    private const OUTFLOW_TYPES = ['sale', 'adjustment_out', 'transfer_out', 'return_out'];

    /** @param array<string,mixed> $data */
    public function postMove(array $data): StockMove
    {
        $companyId = (int) $data['company_id'];
        $contextCompany = CompanyContext::get();
        if ($contextCompany !== null && $contextCompany !== $companyId) {
            throw new RuntimeException('Company context mismatch.');
        }

        if (auth()->check() && ! auth()->user()->companies()->where('companies.id', $companyId)->exists()) {
            throw new RuntimeException('User has no access to company stock.');
        }

        $product = Product::query()->findOrFail((int) $data['product_id']);
        if ($product->product_type !== 'stock') {
            throw new RuntimeException('Service products cannot create stock moves.');
        }

        $qty = (float) $data['qty'];
        $moveType = (string) $data['move_type'];
        $isInflow = in_array($moveType, self::INFLOW_TYPES, true);
        $isOutflow = in_array($moveType, self::OUTFLOW_TYPES, true);

        if (! $isInflow && ! $isOutflow) {
            throw new RuntimeException('Invalid move type.');
        }

        if (str_starts_with($moveType, 'adjustment_') && auth()->check() && ! auth()->user()->hasAnyRole(['admin', 'manager'])) {
            throw new RuntimeException('Only manager/admin can post stock adjustments.');
        }

        if ($isInflow && $qty <= 0) {
            throw new RuntimeException('Inflow moves require positive qty.');
        }
        if ($isOutflow && $qty >= 0) {
            throw new RuntimeException('Outflow moves require negative qty.');
        }

        $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : null;
        if ($isInflow && ($unitCost === null || $unitCost <= 0) && $moveType !== 'return_in') {
            throw new RuntimeException('Inflow moves require unit_cost.');
        }

        if (($isOutflow || $moveType === 'return_in') && ($unitCost === null || $unitCost <= 0)) {
            $unitCost = (float) ProductCost::query()->firstOrCreate(
                ['company_id' => $companyId, 'product_id' => (int) $data['product_id']],
                ['avg_cost' => 0]
            )->avg_cost;
        }

        $totalCost = round(abs($qty) * (float) $unitCost, 4);

        /** @var StockMove $move */
        $move = DB::transaction(function () use ($data, $unitCost, $totalCost, $companyId): StockMove {
            return StockMove::query()->create([
                'company_id' => $companyId,
                'product_id' => (int) $data['product_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'location_id' => $data['location_id'] ?? null,
                'move_type' => (string) $data['move_type'],
                'qty' => (float) $data['qty'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'note' => $data['note'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'created_by_user_id' => auth()->id(),
            ]);
        });

        AuditLog::query()->create([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'action' => 'stock_move.posted',
            'auditable_type' => 'stock_move',
            'auditable_id' => $move->id,
            'payload' => ['move_type' => $move->move_type, 'qty' => $move->qty],
            'created_at' => now(),
        ]);

        if ($isInflow) {
            $this->recalcAverageCost($companyId, (int) $data['product_id']);
        }

        $this->syncWarehouseStock(
            $companyId,
            (int) $data['product_id'],
            (int) $data['warehouse_id']
        );

        $onHand = $this->getOnHand($companyId, (int) $data['product_id'], (int) $data['warehouse_id']);
        if ($onHand < 0) {
            InventoryAlert::query()->create([
                'company_id' => $companyId,
                'product_id' => (int) $data['product_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'current_on_hand' => $onHand,
                'alert_type' => 'negative_stock',
                'created_at' => now(),
            ]);
        }

        return $move;
    }

    public function recalcAverageCost(int $companyId, int $productId): ProductCost
    {
        $totals = StockMove::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereIn('move_type', self::INFLOW_TYPES)
            ->selectRaw('COALESCE(SUM(total_cost),0) as total_cost_sum, COALESCE(SUM(ABS(qty)),0) as qty_sum')
            ->first();

        $qty = (float) ($totals->qty_sum ?? 0);
        $avgCost = $qty > 0 ? round(((float) $totals->total_cost_sum) / $qty, 4) : 0.0;

        /** @var ProductCost $cost */
        $cost = ProductCost::query()->updateOrCreate(
            ['company_id' => $companyId, 'product_id' => $productId],
            ['avg_cost' => $avgCost, 'last_calculated_at' => now()]
        );

        return $cost;
    }

    private function syncWarehouseStock(int $companyId, int $productId, int $warehouseId): void
    {
        $onHand = $this->getOnHand($companyId, $productId, $warehouseId);

        ProductWarehouseStock::query()->updateOrCreate(
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'quantity' => $onHand,
            ]
        );
    }

    public function getOnHand(int $companyId, int $productId, ?int $warehouseId = null): float
    {
        $query = StockMove::query()->where('company_id', $companyId)->where('product_id', $productId);
        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (float) $query->sum('qty');
    }
}
