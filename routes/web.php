<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'website');
Route::view('/about', 'website');
Route::view('/services', 'website');
Route::view('/contact', 'website');

// Application (protected routes)
Route::view('/app/{any}', 'app')->where('any', '.*');

// Fallback for SPA
Route::view('/{any}', 'app')->where('any', '^(?!api).*');

