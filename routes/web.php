<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'website');
Route::view('/contact', 'website');
Route::view('/nav', 'website');
Route::view('/prebook', 'website');
Route::view('/bug', 'website');
Route::view('/pricing', 'website');
Route::view('/specification', 'website');

Route::view('/app', 'app');

Route::view('/app/{any}', 'app')->where('any', '.*');
Route::view('/{any}', 'app')->where('any', '^(?!api).*');

