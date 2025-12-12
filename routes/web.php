<?php

use Illuminate\Support\Facades\Route;

// SPA
Route::view('/{any}', 'app')->where('any', '^(?!api).*');

