<?php

namespace App\Domain\Sales;

use RuntimeException;

final class InvoiceSeriesService
{
    /** @var array<string, int> */
    private array $counters = [];

    public function nextNumber(int $companyId, string $seriesType, string $seriesPrefix): string
    {
        if (!in_array($seriesType, ['T', 'F', 'NC'], true)) {
            throw new RuntimeException('Unsupported series type.');
        }

        $key = sprintf('%d:%s:%s', $companyId, $seriesType, $seriesPrefix);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;

        return sprintf('%s-%06d', $seriesPrefix, $this->counters[$key]);
    }
}
