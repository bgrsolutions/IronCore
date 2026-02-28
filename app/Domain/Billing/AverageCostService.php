<?php

namespace App\Domain\Billing;

final class AverageCostService
{
    /**
     * @param array{on_hand: float, average_cost: float} $state
     * @param array{quantity: float, unit_cost: float} $receipt
     * @return array{on_hand: float, average_cost: float}
     */
    public function applyReceipt(array $state, array $receipt): array
    {
        $currentQty = $state['on_hand'];
        $currentCost = $state['average_cost'];
        $incomingQty = $receipt['quantity'];
        $incomingCost = $receipt['unit_cost'];

        $newQty = $currentQty + $incomingQty;
        if ($newQty <= 0) {
            return ['on_hand' => 0.0, 'average_cost' => 0.0];
        }

        $newAverage = (($currentQty * $currentCost) + ($incomingQty * $incomingCost)) / $newQty;

        return [
            'on_hand' => round($newQty, 4),
            'average_cost' => round($newAverage, 4),
        ];
    }
}
