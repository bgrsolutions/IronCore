<?php

namespace App\Services;

use App\Domain\Inventory\StockService;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesDocument;
use App\Models\SalesDocumentLine;
use App\Models\Warehouse;
use App\Support\Company\CompanyContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SalesDocumentService
{
    public function __construct(private readonly StockService $stockService, private readonly VeriFactuService $veriFactuService)
    {
    }

    public function post(SalesDocument $doc): SalesDocument
    {
        $this->guardPostAccess($doc);

        return DB::transaction(function () use ($doc): SalesDocument {
            $document = SalesDocument::query()->lockForUpdate()->findOrFail($doc->id);

            if ($document->status !== 'draft') {
                throw new RuntimeException('Sales document is already posted or cancelled.');
            }

            if ($document->locked_at !== null) {
                throw new RuntimeException('Posted documents are immutable.');
            }

            $nextNumber = $this->allocateNextNumber((int) $document->company_id, (string) $document->series);
            $lines = $document->lines()->orderBy('line_no')->lockForUpdate()->get();
            $totals = $this->computeTotalsFromLines($lines);

            foreach ($lines as $line) {
                $this->createInventoryMoves($document, $line);
            }

            $fullNumber = sprintf('%s-%s-%06d', $document->series, now()->format('Y'), $nextNumber);
            $document->number = $nextNumber;
            $document->full_number = $fullNumber;
            $document->net_total = $totals['net_total'];
            $document->tax_total = $totals['tax_total'];
            $document->gross_total = $totals['gross_total'];

            $payload = $this->buildImmutablePayload($document, $lines, $nextNumber, $totals);
            $previousHash = $this->veriFactuService->resolvePreviousHash($document);
            $canonical = $this->veriFactuService->canonicalizePayload($document, $payload, $previousHash, $fullNumber);
            $hash = $this->veriFactuService->computeHash($canonical);
            $qrPayload = $this->veriFactuService->generateQrPayload($document, $hash, $fullNumber);

            $document->update([
                'number' => $nextNumber,
                'full_number' => $fullNumber,
                'status' => 'posted',
                'net_total' => $totals['net_total'],
                'tax_total' => $totals['tax_total'],
                'gross_total' => $totals['gross_total'],
                'posted_at' => now(),
                'locked_at' => now(),
                'immutable_payload' => $payload,
                'previous_hash' => $previousHash,
                'hash' => $hash,
                'qr_payload' => $qrPayload,
            ]);

            $this->veriFactuService->recordEvent((int) $document->company_id, (int) $document->id, 'sales_document.posted', [
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'series' => $document->series,
            ]);

            AuditLog::query()->create([
                'company_id' => $document->company_id,
                'user_id' => auth()->id(),
                'action' => 'sales_document.posted',
                'auditable_type' => 'sales_document',
                'auditable_id' => $document->id,
                'payload' => ['full_number' => $document->full_number],
                'created_at' => now(),
            ]);

            return $document->refresh();
        });
    }

    public function allocateNextNumber(int $companyId, string $series): int
    {
        $current = SalesDocument::query()
            ->where('company_id', $companyId)
            ->where('series', $series)
            ->where('status', 'posted')
            ->lockForUpdate()
            ->max('number');

        return ((int) $current) + 1;
    }

    /** @param Collection<int, SalesDocumentLine> $lines */
    public function computeTotalsFromLines(Collection $lines): array
    {
        return [
            'net_total' => round((float) $lines->sum('line_net'), 2),
            'tax_total' => round((float) $lines->sum('line_tax'), 2),
            'gross_total' => round((float) $lines->sum('line_gross'), 2),
        ];
    }

    /** @param Collection<int, SalesDocumentLine> $lines */
    public function buildImmutablePayload(SalesDocument $document, Collection $lines, int $nextNumber, array $totals): array
    {
        return [
            'company_id' => (int) $document->company_id,
            'doc_type' => (string) $document->doc_type,
            'series' => (string) $document->series,
            'number' => $nextNumber,
            'issue_date' => optional($document->issue_date)->toISOString(),
            'currency' => (string) $document->currency,
            'lines' => $lines->map(fn (SalesDocumentLine $line) => [
                'line_no' => (int) $line->line_no,
                'product_id' => $line->product_id ? (int) $line->product_id : null,
                'description' => (string) $line->description,
                'qty' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
                'line_net' => (float) $line->line_net,
                'line_tax' => (float) $line->line_tax,
                'line_gross' => (float) $line->line_gross,
            ])->values()->all(),
            'totals' => [
                'net' => (float) $totals['net_total'],
                'tax' => (float) $totals['tax_total'],
                'gross' => (float) $totals['gross_total'],
            ],
        ];
    }

    public function cancelDraft(SalesDocument $document, string $reason): void
    {
        if ($document->status !== 'draft') {
            throw new RuntimeException('Posted documents require credit note correction.');
        }

        $this->assertEditable($document);

        $document->update(['status' => 'cancelled', 'cancel_reason' => $reason, 'cancelled_at' => now()]);

        AuditLog::query()->create([
            'company_id' => $document->company_id,
            'user_id' => auth()->id(),
            'action' => 'sales_document.cancelled',
            'auditable_type' => 'sales_document',
            'auditable_id' => $document->id,
            'payload' => ['reason' => $reason],
            'created_at' => now(),
        ]);
    }

    public function createCreditNote(SalesDocument $original): SalesDocument
    {
        if ($original->status !== 'posted') {
            throw new RuntimeException('Credit notes require a posted source document.');
        }

        $credit = SalesDocument::query()->create([
            'company_id' => $original->company_id,
            'customer_id' => $original->customer_id,
            'doc_type' => 'credit_note',
            'series' => 'NC',
            'status' => 'draft',
            'issue_date' => now(),
            'currency' => $original->currency,
            'related_document_id' => $original->id,
            'source' => 'manual',
            'created_by_user_id' => auth()->id(),
        ]);

        $lineNo = 1;
        foreach ($original->lines()->orderBy('line_no')->get() as $line) {
            $credit->lines()->create([
                'line_no' => $lineNo++,
                'product_id' => $line->product_id,
                'description' => 'Credit: '.$line->description,
                'qty' => abs((float) $line->qty),
                'unit_price' => -1 * abs((float) $line->unit_price),
                'tax_rate' => $line->tax_rate,
                'line_net' => -1 * abs((float) $line->line_net),
                'line_tax' => -1 * abs((float) $line->line_tax),
                'line_gross' => -1 * abs((float) $line->line_gross),
            ]);
        }

        AuditLog::query()->create([
            'company_id' => $original->company_id,
            'user_id' => auth()->id(),
            'action' => 'sales_document.credit_note_created',
            'auditable_type' => 'sales_document',
            'auditable_id' => $credit->id,
            'payload' => ['related_document_id' => $original->id],
            'created_at' => now(),
        ]);

        return $credit;
    }

    public function assertEditable(SalesDocument $document): void
    {
        if ($document->locked_at !== null || $document->status === 'posted') {
            throw new RuntimeException('Posted documents are immutable.');
        }
    }

    private function createInventoryMoves(SalesDocument $document, SalesDocumentLine $line): void
    {
        if (! $line->product_id) {
            return;
        }

        $product = Product::query()->find($line->product_id);
        if (! $product || $product->product_type !== 'stock') {
            return;
        }

        $warehouseId = Warehouse::query()
            ->where('company_id', $document->company_id)
            ->where('is_default', true)
            ->value('id');
        if (! $warehouseId) {
            return;
        }

        $locationId = Location::query()
            ->where('company_id', $document->company_id)
            ->where('warehouse_id', $warehouseId)
            ->where('is_default', true)
            ->value('id');

        $isCredit = $document->doc_type === 'credit_note';
        $qty = $isCredit ? abs((float) $line->qty) : -1 * abs((float) $line->qty);
        $moveType = $isCredit ? 'return_in' : 'sale';

        $move = $this->stockService->postMove([
            'company_id' => $document->company_id,
            'product_id' => $line->product_id,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'move_type' => $moveType,
            'qty' => $qty,
            'occurred_at' => now(),
            'reference_type' => 'sales_document_line',
            'reference_id' => $line->id,
            'note' => 'Auto move from sales document '.$document->id,
        ]);

        $line->update(['cost_unit' => $move->unit_cost, 'cost_total' => $move->total_cost]);
    }

    private function guardPostAccess(SalesDocument $document): void
    {
        $ctx = CompanyContext::get();
        if ($ctx !== null && $ctx !== (int) $document->company_id) {
            throw new RuntimeException('Company context mismatch.');
        }

        if (auth()->check() && ! auth()->user()->companies()->where('companies.id', $document->company_id)->exists()) {
            throw new RuntimeException('Cross-company posting is forbidden.');
        }
    }
}
