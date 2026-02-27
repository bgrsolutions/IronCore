<?php

namespace App\Domain\Billing;

use DateTimeImmutable;

final class DeadStockAgeingService
{
    /**
     * @param array<int, array{last_moved_at: string, quantity: float}> $items
     * @return array<string, int>
     */
    public function buckets(array $items, string $asOfDate): array
    {
        $asOf = new DateTimeImmutable($asOfDate);

        $buckets = [
            '30' => 0,
            '60' => 0,
            '90' => 0,
            '180' => 0,
        ];

        foreach ($items as $item) {
            if ($item['quantity'] <= 0) {
                continue;
            }

            $days = (int) $asOf->diff(new DateTimeImmutable($item['last_moved_at']))->format('%a');
            if ($days >= 180) {
                $buckets['180']++;
            } elseif ($days >= 90) {
                $buckets['90']++;
            } elseif ($days >= 60) {
                $buckets['60']++;
            } elseif ($days >= 30) {
                $buckets['30']++;
            }
        }

        return $buckets;
    }
}
