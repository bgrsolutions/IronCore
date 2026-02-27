<?php

namespace App\Domain\Repairs;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\PublicToken;
use App\Models\Repair;
use App\Models\RepairFeedback;
use App\Models\RepairPickup;
use App\Models\RepairStatusHistory;
use App\Models\RepairSignature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class RepairPublicFlowService
{
    private const PURPOSE_INTAKE = 'repair_intake_signature';
    private const PURPOSE_PICKUP = 'repair_pickup_signature';
    private const PURPOSE_FEEDBACK = 'repair_feedback';

    public function generateToken(Repair $repair, string $purpose, ?int $minutes = null): PublicToken
    {
        if (! in_array($purpose, [self::PURPOSE_INTAKE, self::PURPOSE_PICKUP, self::PURPOSE_FEEDBACK], true)) {
            throw new RuntimeException('Unsupported token purpose.');
        }

        return PublicToken::query()->create([
            'company_id' => $repair->company_id,
            'repair_id' => $repair->id,
            'purpose' => $purpose,
            'token' => Str::random(60),
            'expires_at' => now()->addMinutes($minutes ?? (int) config('repairs.public_token_ttl_minutes', 30)),
            'created_by_user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function resolveValidToken(string $token): PublicToken
    {
        $record = PublicToken::query()->withoutGlobalScopes()->where('token', $token)->first();
        if (! $record) {
            throw new RuntimeException('Invalid token.');
        }
        if ($record->used_at !== null) {
            throw new RuntimeException('Token already used.');
        }
        if ($record->expires_at->isPast()) {
            throw new RuntimeException('Token expired.');
        }

        return $record;
    }

    public function submitSignature(PublicToken $token, string $base64Png, ?string $signerName, string $ip, ?string $ua): array
    {
        return DB::transaction(function () use ($token, $base64Png, $signerName, $ip, $ua): array {
            $lockedToken = PublicToken::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($token->id);
            $this->assertConsumable($lockedToken);

            if (! in_array($lockedToken->purpose, [self::PURPOSE_INTAKE, self::PURPOSE_PICKUP], true)) {
                throw new RuntimeException('Token purpose does not allow signature submission.');
            }

            $repair = Repair::query()->withoutGlobalScopes()->with('linkedSalesDocument.lines')->findOrFail($lockedToken->repair_id);

            if ($lockedToken->purpose === self::PURPOSE_PICKUP && (bool) config('repairs.require_invoice_before_pickup', true)) {
                if (! $repair->linked_sales_document_id || optional($repair->linkedSalesDocument)->status !== 'posted') {
                    throw new RuntimeException('Invoice must be posted before pickup.');
                }
            }

            $raw = preg_replace('/^data:image\/png;base64,/', '', $base64Png);
            $bytes = base64_decode((string) $raw, true);
            if ($bytes === false) {
                throw new RuntimeException('Invalid signature payload.');
            }

            $type = $lockedToken->purpose === self::PURPOSE_PICKUP ? 'pickup' : 'intake';
            $disk = config('filesystems.default');
            $path = sprintf('%d/repairs/%d/%s/%s.png', $repair->company_id, $repair->id, $type, now()->format('YmdHisv'));
            Storage::disk($disk)->put($path, $bytes);

            $signature = RepairSignature::query()->create([
                'repair_id' => $repair->id,
                'signature_type' => $type,
                'signer_name' => $signerName,
                'signed_at' => now(),
                'signature_image_path' => $path,
                'signature_hash' => hash('sha256', $bytes),
                'ip_address' => $ip,
                'user_agent' => $ua,
                'created_at' => now(),
            ]);

            $feedbackToken = null;
            if ($type === 'pickup') {
                RepairPickup::query()->create([
                    'repair_id' => $repair->id,
                    'picked_up_at' => now(),
                    'pickup_method' => 'customer',
                    'pickup_confirmed' => true,
                    'pickup_signature_id' => $signature->id,
                    'created_at' => now(),
                ]);

                $from = $repair->status;
                if ($from !== 'collected') {
                    $repair->update(['status' => 'collected']);
                    RepairStatusHistory::query()->create([
                        'company_id' => $repair->company_id,
                        'repair_id' => $repair->id,
                        'from_status' => $from,
                        'to_status' => 'collected',
                        'changed_by' => auth()->id(),
                        'reason' => 'pickup_signature_completed',
                        'changed_at' => now(),
                    ]);
                }

                $feedbackToken = $this->generateToken($repair, self::PURPOSE_FEEDBACK);
            }

            $lockedToken->update(['used_at' => now()]);

            AuditLog::query()->create([
                'company_id' => $repair->company_id,
                'user_id' => auth()->id(),
                'action' => 'repair.signature_submitted',
                'auditable_type' => 'repair',
                'auditable_id' => $repair->id,
                'payload' => ['signature_type' => $type, 'token_purpose' => $lockedToken->purpose],
                'created_at' => now(),
            ]);

            return ['signature' => $signature, 'feedbackToken' => $feedbackToken];
        });
    }

    public function submitFeedback(PublicToken $token, int $rating, ?string $comment): RepairFeedback
    {
        return DB::transaction(function () use ($token, $rating, $comment): RepairFeedback {
            $lockedToken = PublicToken::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($token->id);
            $this->assertConsumable($lockedToken);

            if ($lockedToken->purpose !== self::PURPOSE_FEEDBACK) {
                throw new RuntimeException('Token purpose does not allow feedback submission.');
            }

            $feedback = RepairFeedback::query()->create([
                'repair_id' => $lockedToken->repair_id,
                'rating' => $rating,
                'comment' => $comment,
                'submitted_at' => now(),
                'created_at' => now(),
            ]);

            $lockedToken->update(['used_at' => now()]);

            return $feedback;
        });
    }

    public function generatePickupReceipt(Repair $repair): Document
    {
        $pickup = $repair->pickups()->latest('picked_up_at')->first();
        $signature = $pickup?->signature;
        if (! $pickup || ! $signature || $signature->signature_type !== 'pickup') {
            throw new RuntimeException('Cannot generate receipt without pickup signature.');
        }

        $repair->loadMissing('customer', 'linkedSalesDocument.lines', 'company');
        $salesDocument = $repair->linkedSalesDocument;
        $lines = $salesDocument?->lines ?? collect();

        $pdf = Pdf::loadView('pdf.repair-pickup-receipt', [
            'repair' => $repair,
            'pickup' => $pickup,
            'salesDocument' => $salesDocument,
            'lines' => $lines,
            'signaturePath' => Storage::disk(config('filesystems.default'))->path($signature->signature_image_path),
        ])->output();

        $disk = config('filesystems.default');
        $path = sprintf('%d/repairs/%d/pickup-receipts/%s.pdf', $repair->company_id, $repair->id, now()->format('YmdHisv'));
        Storage::disk($disk)->put($path, $pdf);

        $document = Document::query()->create([
            'company_id' => $repair->company_id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'pickup-receipt-repair-'.$repair->id.'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'status' => 'ready',
            'uploaded_at' => now(),
        ]);

        DocumentAttachment::query()->create([
            'company_id' => $repair->company_id,
            'document_id' => $document->id,
            'attachable_type' => Repair::class,
            'attachable_id' => $repair->id,
            'created_at' => now(),
        ]);

        AuditLog::query()->create([
            'company_id' => $repair->company_id,
            'user_id' => auth()->id(),
            'action' => 'repair.pickup_receipt_generated',
            'auditable_type' => 'repair',
            'auditable_id' => $repair->id,
            'payload' => ['document_id' => $document->id],
            'created_at' => now(),
        ]);

        return $document;
    }

    private function assertConsumable(PublicToken $token): void
    {
        if ($token->used_at !== null) {
            throw new RuntimeException('Token already used.');
        }

        if ($token->expires_at->isPast()) {
            throw new RuntimeException('Token expired.');
        }
    }
}
