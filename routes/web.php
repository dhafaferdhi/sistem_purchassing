<?php

use App\Http\Controllers\QuotationExportController;
use App\Http\Controllers\QuotationPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/quotations/export/csv', [QuotationExportController::class, 'exportCsv'])
    ->name('quotations.export.csv')
    ->middleware(['auth']);

Route::get('/quotations/{quotation}/pdf', [QuotationPdfController::class, 'download'])
    ->name('quotations.pdf')
    ->middleware(['auth']);
