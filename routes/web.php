<?php

use App\Http\Controllers\Api\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/documents/public/{document}', [DocumentController::class, 'publicDocument'])
    ->name('documents.public')
    ->middleware('signed');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*')->name('spa');
