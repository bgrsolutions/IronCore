<?php

use App\Http\Controllers\Api\PrestaShopController;
use App\Http\Controllers\Api\SupplierStockImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('integration.token')->group(function (): void {
    Route::post('/integrations/prestashop/order-paid', [PrestaShopController::class, 'orderPaid']);
    Route::post('/integrations/supplier-stock/import', SupplierStockImportController::class);
});
