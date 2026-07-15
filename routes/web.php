<?php

use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/documents/public/{document}', [DocumentController::class, 'publicDocument'])
    ->name('documents.public')
    ->middleware('signed.url:all')->middleware('expires');

Route::get('/invoices/public/{invoice}', [InvoiceController::class, 'publicInvoice'])
    ->name('invoices.public')
    ->middleware('signed.url:all')->middleware('expires');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*')->name('spa');
