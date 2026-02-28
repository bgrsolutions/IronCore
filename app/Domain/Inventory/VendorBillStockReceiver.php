<?php

namespace App\Domain\Inventory;

use App\Domain\Audit\AuditLogger;
use App\Domain\Billing\AverageCostService;
use RuntimeException;

final class VendorBillStockReceiver
{
    public function __construct(
        private readonly AverageCostService $averageCostService,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array<string, mixed> $vendorBill
     * @param array<int, array<string, mixed>> $billLines
     * @param array<int, array{on_hand: float, average_cost: float}> $productStates
     * @return array{stock_moves: array<int, array<string, mixed>>, product_states: array<int, array{on_hand: float, average_cost: float}>}
     */
    public function receive(array $vendorBill, array $billLines, array $productStates, int $userId): array
    {
        if (($vendorBill['status'] ?? null) !== 'posted') {
            throw new RuntimeException('Stock can only be received from posted vendor bills.');
        }

        $stockMoves = [];

        foreach ($billLines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            $unitCost = (float) ($line['unit_price'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $state = $productStates[$productId] ?? ['on_hand' => 0.0, 'average_cost' => 0.0];
            $productStates[$productId] = $this->averageCostService->applyReceipt($state, [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ]);

            $move = [
                'company_id' => (int) $vendorBill['company_id'],
                'product_id' => $productId,
                'direction' => 'in',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reason' => 'vendor_bill_receipt',
                'source_type' => 'vendor_bill',
                'source_id' => (int) $vendorBill['id'],
                'moved_at' => gmdate('c'),
            ];

            $stockMoves[] = $move;
        }

        $this->auditLogger->record(
            companyId: (int) $vendorBill['company_id'],
            action: 'inventory.received_from_vendor_bill',
            auditableType: 'vendor_bill',
            auditableId: (int) $vendorBill['id'],
            userId: $userId,
            payload: [
                'moves_count' => count($stockMoves),
                'product_ids' => array_values(array_unique(array_map(static fn (array $m): int => (int) $m['product_id'], $stockMoves))),
            ]
        );

        return [
            'stock_moves' => $stockMoves,
            'product_states' => $productStates,
        ];
    }
}
