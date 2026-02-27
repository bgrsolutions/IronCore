<?php

namespace App\Domain\Repairs;

use RuntimeException;

final class RepairTimerService
{
    private const ALLOWED_MINUTES = [15, 30, 60];

    /**
     * @param array<string, mixed> $repair
     * @return array<string, mixed>
     */
    public function addLabourEntry(array $repair, int $minutes, int $userId): array
    {
        if (!in_array($minutes, self::ALLOWED_MINUTES, true)) {
            throw new RuntimeException('Only 15, 30, or 60 minute labour entries are allowed.');
        }

        $entries = $repair['time_entries'] ?? [];
        $entries[] = [
            'minutes' => $minutes,
            'labour_product_code' => sprintf('LABOUR_%d', $minutes),
            'user_id' => $userId,
        ];

        $repair['time_entries'] = $entries;

        return $repair;
    }
}
