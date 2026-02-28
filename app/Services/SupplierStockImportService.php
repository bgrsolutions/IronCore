<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierStockSnapshot;
use App\Models\SupplierStockSnapshotItem;

final class SupplierStockImportService
{
    /** @param array<int,array<string,mixed>> $items */
    public function import(int $companyId, int $supplierId, string $warehouseName, array $items, string $source = 'import'): SupplierStockSnapshot
    {
        $snapshot = SupplierStockSnapshot::query()->create([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'warehouse_name' => $warehouseName,
            'snapshot_at' => now(),
            'source' => $source,
            'created_at' => now(),
        ]);

        foreach ($items as $item) {
            $matchedProduct = $this->matchProduct(
                barcode: $this->strOrNull($item['barcode'] ?? null),
                sku: $this->strOrNull($item['sku'] ?? null),
                supplierSku: $this->strOrNull($item['supplier_sku'] ?? null),
            );

            SupplierStockSnapshotItem::query()->create([
                'supplier_stock_snapshot_id' => $snapshot->id,
                'product_id' => $matchedProduct?->id,
                'supplier_sku' => $this->strOrNull($item['supplier_sku'] ?? null),
                'barcode' => $this->strOrNull($item['barcode'] ?? null),
                'product_name' => $this->strOrNull($item['product_name'] ?? $item['name'] ?? null),
                'qty_available' => (float) ($item['qty_available'] ?? $item['qty'] ?? 0),
                'unit_cost' => isset($item['unit_cost']) && $item['unit_cost'] !== '' ? (float) $item['unit_cost'] : null,
                'currency' => (string) ($item['currency'] ?? 'EUR'),
                'created_at' => now(),
            ]);
        }

        return $snapshot->load('items');
    }

    /** @return array<int,array<string,mixed>> */
    public function parseCsv(string $csvContent, array $columnMap): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csvContent)) ?: [];
        if ($lines === []) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $assoc = [];
            foreach ($headers as $idx => $header) {
                $assoc[trim((string) $header)] = $cells[$idx] ?? null;
            }

            $rows[] = [
                'supplier_sku' => $assoc[$columnMap['supplier_sku'] ?? 'supplier_sku'] ?? null,
                'barcode' => $assoc[$columnMap['barcode'] ?? 'barcode'] ?? null,
                'product_name' => $assoc[$columnMap['product_name'] ?? 'product_name'] ?? null,
                'qty_available' => $assoc[$columnMap['qty_available'] ?? 'qty_available'] ?? 0,
                'unit_cost' => $assoc[$columnMap['unit_cost'] ?? 'unit_cost'] ?? null,
                'currency' => $assoc[$columnMap['currency'] ?? 'currency'] ?? 'EUR',
                'sku' => $assoc[$columnMap['sku'] ?? 'sku'] ?? null,
            ];
        }

        return $rows;
    }

    private function matchProduct(?string $barcode, ?string $sku, ?string $supplierSku): ?Product
    {
        if ($barcode) {
            $found = Product::query()->where('barcode', $barcode)->first();
            if ($found) {
                return $found;
            }
        }

        if ($sku) {
            $found = Product::query()->where('sku', $sku)->first();
            if ($found) {
                return $found;
            }
        }

        if ($supplierSku) {
            return Product::query()->where('sku', $supplierSku)->first();
        }

        return null;
    }

    private function strOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }
}
