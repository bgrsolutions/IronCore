<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\PrestaShopIngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestaShopController extends Controller
{
    public function orderPaid(Request $request, PrestaShopIngestService $ingestService): JsonResponse
    {
        $companyId = (int) app('integration.company_id');
        $autoPost = (bool) config('services.prestashop.auto_post', false);
        $document = $ingestService->ingest($request->all(), $companyId, $autoPost);

        return response()->json(['ok' => true, 'sales_document_id' => $document->id, 'status' => $document->status], 201);
    }
}
