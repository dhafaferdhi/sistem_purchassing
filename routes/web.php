<?php

use App\Http\Controllers\PriceUpdateController;
use App\Http\Controllers\PurchaseOrderPdfController;
use App\Http\Controllers\QuotationExportController;
use App\Http\Controllers\QuotationPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/purchasing/price-update', [PriceUpdateController::class, 'index'])
        ->name('purchasing.price-update');
    Route::post('/purchasing/price-update', [PriceUpdateController::class, 'update'])
        ->name('purchasing.price-update.submit');
    Route::post('/purchasing/price-update/preview', [PriceUpdateController::class, 'preview'])
        ->name('purchasing.price-update.preview');
});

Route::get('/quotations/export/csv', [QuotationExportController::class, 'exportCsv'])
    ->name('quotations.export.csv')
    ->middleware(['auth']);

Route::get('/quotations/{quotation}/pdf', [QuotationPdfController::class, 'download'])
    ->name('quotations.pdf')
    ->middleware(['auth']);

Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderPdfController::class, 'download'])
    ->name('purchase-orders.pdf')
    ->middleware(['auth']);
