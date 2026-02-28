<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupplierStockImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierStockImportController extends Controller
{
    public function __invoke(Request $request, SupplierStockImportService $service): JsonResponse
    {
        $companyId = (int) app('integration.company_id');
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'warehouse_name' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_sku' => ['nullable', 'string', 'max:80'],
            'items.*.barcode' => ['nullable', 'string', 'max:80'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.qty_available' => ['required', 'numeric'],
            'items.*.unit_cost' => ['nullable', 'numeric'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
        ]);

        $snapshot = $service->import(
            companyId: $companyId,
            supplierId: (int) $data['supplier_id'],
            warehouseName: (string) $data['warehouse_name'],
            items: $data['items'],
            source: 'api',
        );

        return response()->json([
            'snapshot_id' => $snapshot->id,
            'items_count' => $snapshot->items->count(),
        ], 201);
    }
}
