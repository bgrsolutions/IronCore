<?php

namespace App\Domain\Repairs;

use App\Domain\Audit\AuditLogger;

final class RepairPartsService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $repair
     * @param array<int, array<string, mixed>> $parts
     * @return array<int, array<string, mixed>>
     */
    public function consumeParts(array $repair, array $parts, int $userId): array
    {
        $moves = [];

        foreach ($parts as $part) {
            $quantity = (float) ($part['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $moves[] = [
                'company_id' => (int) $repair['company_id'],
                'product_id' => (int) $part['product_id'],
                'direction' => 'out',
                'quantity' => $quantity,
                'unit_cost' => (float) ($part['unit_cost'] ?? 0),
                'reason' => 'repair_parts_consumption',
                'source_type' => 'repair',
                'source_id' => (int) $repair['id'],
            ];
        }

        $this->auditLogger->record(
            companyId: (int) $repair['company_id'],
            action: 'repair.parts_consumed',
            auditableType: 'repair',
            auditableId: (int) $repair['id'],
            userId: $userId,
            payload: [
                'moves_count' => count($moves),
            ]
        );

        return $moves;
    }
}
