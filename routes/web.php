<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\RepairTabletController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/documents/{document}/download', DocumentDownloadController::class)
    ->middleware('auth')
    ->name('documents.download');

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/p/repairs/{token}', [RepairTabletController::class, 'show'])->name('public.repairs.show');
    Route::post('/p/repairs/{token}/sign', [RepairTabletController::class, 'sign'])->name('public.repairs.sign');
    Route::post('/p/repairs/{token}/feedback', [RepairTabletController::class, 'feedback'])->name('public.repairs.feedback');
});
