<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SalesDocument;
use App\Models\VerifactuExport;
use App\Services\VeriFactuService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerifactuExportCommand extends Command
{
    protected $signature = 'verifactu:export {--company=} {--from=} {--to=}';

    protected $description = 'Generate VeriFactu export registry file for posted sales documents.';

    public function handle(VeriFactuService $veriFactuService): int
    {
        $companyId = (int) $this->option('company');
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');

        if (! $companyId || ! $from || ! $to) {
            $this->error('Missing required options: --company, --from, --to');

            return self::FAILURE;
        }

        $fromAt = Carbon::parse($from)->startOfDay();
        $toAt = Carbon::parse($to)->endOfDay();

        $documents = SalesDocument::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween('issue_date', [$fromAt, $toAt])
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get();

        $records = $documents->map(function (SalesDocument $doc) use ($veriFactuService): array {
            $canonical = $veriFactuService->canonicalizePayload(
                $doc,
                (array) ($doc->immutable_payload ?? []),
                $doc->previous_hash,
                (string) $doc->full_number
            );

            return [
                'sales_document_id' => $doc->id,
                'full_number' => $doc->full_number,
                'status' => $doc->status,
                'posted_at' => optional($doc->posted_at)->toISOString(),
                'hash' => $doc->hash,
                'previous_hash' => $doc->previous_hash,
                'qr_payload' => $doc->qr_payload,
                'canonical_payload' => $canonical,
            ];
        })->values()->all();

        $payload = [
            'export_type' => 'verifactu_records',
            'company_id' => $companyId,
            'period_start' => $fromAt->toISOString(),
            'period_end' => $toAt->toISOString(),
            'generated_at' => now()->toISOString(),
            'record_count' => count($records),
            'records' => $records,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $path = sprintf('verifactu/%d/verifactu_records_%s_%s_%s.json', $companyId, $fromAt->format('Ymd'), $toAt->format('Ymd'), now()->format('His'));
        Storage::disk('local')->put($path, (string) $json);

        DB::transaction(function () use ($companyId, $fromAt, $toAt, $path, $json, $documents): void {
            $export = VerifactuExport::query()->create([
                'company_id' => $companyId,
                'export_type' => 'verifactu_records',
                'period_start' => $fromAt,
                'period_end' => $toAt,
                'generated_at' => now(),
                'generated_by_user_id' => auth()->id(),
                'file_path' => $path,
                'file_hash' => hash('sha256', (string) $json),
                'record_count' => $documents->count(),
                'status' => 'generated',
            ]);

            foreach ($documents as $document) {
                $export->items()->create([
                    'sales_document_id' => $document->id,
                    'included_at' => now(),
                    'created_at' => now(),
                ]);
            }

            AuditLog::query()->create([
                'company_id' => $companyId,
                'user_id' => auth()->id(),
                'action' => 'verifactu.export.generated',
                'auditable_type' => 'verifactu_export',
                'auditable_id' => $export->id,
                'payload' => ['file_path' => $path, 'record_count' => $documents->count()],
                'created_at' => now(),
            ]);
        });

        $this->info('Export generated: '.$path);

        return self::SUCCESS;
    }
}
