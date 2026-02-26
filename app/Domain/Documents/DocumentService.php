<?php

namespace App\Domain\Documents;

use App\Domain\Audit\AuditLogger;

final class DocumentService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $documentable
     * @param array<string, mixed> $documentInput
     * @return array<string, mixed>
     */
    public function attach(array $documentable, array $documentInput, int $userId): array
    {
        $document = [
            'id' => $documentInput['id'] ?? random_int(1000, 999999),
            'company_id' => (int) $documentable['company_id'],
            'supplier_id' => $documentInput['supplier_id'] ?? null,
            'documentable_type' => (string) $documentable['type'],
            'documentable_id' => (int) $documentable['id'],
            'disk' => $documentInput['disk'] ?? 's3',
            'path' => (string) $documentInput['path'],
            'original_name' => (string) $documentInput['original_name'],
            'mime_type' => (string) $documentInput['mime_type'],
            'size_bytes' => (int) $documentInput['size_bytes'],
            'status' => $documentInput['status'] ?? 'active',
            'document_date' => $documentInput['document_date'] ?? null,
            'uploaded_at' => gmdate('c'),
        ];

        $this->auditLogger->record(
            companyId: (int) $document['company_id'],
            action: 'document.attached',
            auditableType: $document['documentable_type'],
            auditableId: (int) $document['documentable_id'],
            userId: $userId,
            payload: [
                'document_id' => $document['id'],
                'path' => $document['path'],
                'original_name' => $document['original_name'],
            ]
        );

        return $document;
    }
}
