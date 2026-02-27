<?php

namespace App\Domain\Sales;

use App\Domain\Inventory\StockService;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\SalesDocument;
use App\Models\SalesDocumentLine;
use App\Support\Company\CompanyContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SalesDocumentService
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function post(SalesDocument $document): SalesDocument
    {
        if ($document->status !== 'draft') {
            throw new RuntimeException('Only draft documents can be posted.');
        }

        $ctx = CompanyContext::get();
        if ($ctx !== null && $ctx !== (int) $document->company_id) {
            throw new RuntimeException('Company context mismatch.');
        }
        if (auth()->check() && ! auth()->user()->companies()->where('companies.id', $document->company_id)->exists()) {
            throw new RuntimeException('Cross-company posting is forbidden.');
        }

        return DB::transaction(function () use ($document): SalesDocument {
            $current = SalesDocument::query()
                ->where('company_id', $document->company_id)
                ->where('series', $document->series)
                ->lockForUpdate()
                ->max('number');

            $next = ((int) $current) + 1;
            $lines = $document->lines()->orderBy('line_no')->get();
            $net = (float) $lines->sum('line_net');
            $tax = (float) $lines->sum('line_tax');
            $gross = (float) $lines->sum('line_gross');

            foreach ($lines as $line) {
                $this->applyInventoryAndCost($document, $line);
            }

            $payload = [
                'company_id' => $document->company_id,
                'doc_type' => $document->doc_type,
                'series' => $document->series,
                'number' => $next,
                'issue_date' => optional($document->issue_date)->toISOString(),
                'lines' => $lines->map(fn (SalesDocumentLine $l) => [
                    'line_no' => $l->line_no,
                    'product_id' => $l->product_id,
                    'description' => $l->description,
                    'qty' => (float) $l->qty,
                    'unit_price' => (float) $l->unit_price,
                    'tax_rate' => (float) $l->tax_rate,
                    'line_net' => (float) $l->line_net,
                    'line_tax' => (float) $l->line_tax,
                    'line_gross' => (float) $l->line_gross,
                ])->all(),
                'totals' => ['net' => round($net, 2), 'tax' => round($tax, 2), 'gross' => round($gross, 2)],
            ];

            $document->update([
                'number' => $next,
                'full_number' => sprintf('%s-%s-%06d', $document->series, now()->format('Y'), $next),
                'status' => 'posted',
                'net_total' => round($net, 2),
                'tax_total' => round($tax, 2),
                'gross_total' => round($gross, 2),
                'posted_at' => now(),
                'locked_at' => now(),
                'immutable_payload' => $payload,
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

    public function cancelDraft(SalesDocument $document, string $reason): void
    {
        if ($document->status !== 'draft') {
            throw new RuntimeException('Posted documents require credit note correction.');
        }

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
        foreach ($original->lines as $line) {
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

    private function applyInventoryAndCost(SalesDocument $document, SalesDocumentLine $line): void
    {
        if (! $line->product_id) {
            return;
        }

        $product = Product::query()->find($line->product_id);
        if (! $product || $product->product_type !== 'stock') {
            return;
        }

        $warehouseId = \App\Models\Warehouse::query()->where('company_id', $document->company_id)->where('is_default', true)->value('id');
        $locationId = \App\Models\Location::query()->where('company_id', $document->company_id)->where('warehouse_id', $warehouseId)->where('is_default', true)->value('id');

        if (! $warehouseId) {
            return;
        }

        $qty = $document->doc_type === 'credit_note' ? abs((float) $line->qty) : -1 * abs((float) $line->qty);
        $moveType = $document->doc_type === 'credit_note' ? 'return_in' : 'sale';

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
}
