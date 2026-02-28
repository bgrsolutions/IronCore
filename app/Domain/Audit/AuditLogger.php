<?php

namespace App\Domain\Audit;

final class AuditLogger
{
    /** @var array<int, array<string, mixed>> */
    private array $events = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function record(int $companyId, string $action, string $auditableType, int $auditableId, ?int $userId = null, array $payload = []): void
    {
        $this->events[] = [
            'company_id' => $companyId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'payload' => $payload,
            'created_at' => gmdate('c'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->events;
    }
}
