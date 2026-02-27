<?php

namespace App\Domain\Documents;

use App\Domain\Audit\AuditLogger;

final class DocumentService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $attachable
     * @param array<string, mixed> $documentInput
     * @return array{document: array<string, mixed>, attachment: array<string, mixed>}
     */
    public function attach(array $attachable, array $documentInput, int $userId): array
    {
        $document = [
            'id' => $documentInput['id'] ?? random_int(1000, 999999),
            'company_id' => (int) $attachable['company_id'],
            'supplier_id' => $documentInput['supplier_id'] ?? null,
            'disk' => $documentInput['disk'] ?? 's3',
            'path' => (string) $documentInput['path'],
            'original_name' => (string) $documentInput['original_name'],
            'mime_type' => (string) $documentInput['mime_type'],
            'size_bytes' => (int) $documentInput['size_bytes'],
            'status' => $documentInput['status'] ?? 'active',
            'document_date' => $documentInput['document_date'] ?? null,
            'uploaded_at' => gmdate('c'),
        ];

        $attachment = [
            'company_id' => (int) $attachable['company_id'],
            'document_id' => (int) $document['id'],
            'attachable_type' => (string) $attachable['type'],
            'attachable_id' => (int) $attachable['id'],
            'created_at' => gmdate('c'),
        ];

        $this->auditLogger->record(
            companyId: (int) $document['company_id'],
            action: 'document.attached',
            auditableType: $attachment['attachable_type'],
            auditableId: (int) $attachment['attachable_id'],
            userId: $userId,
            payload: [
                'document_id' => $document['id'],
                'path' => $document['path'],
                'original_name' => $document['original_name'],
            ]
        );

        return ['document' => $document, 'attachment' => $attachment];
    }
}
