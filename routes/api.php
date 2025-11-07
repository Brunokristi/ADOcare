<?php

use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\ApiTokenAuth;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // Protected auth routes (require Bearer token)
    Route::middleware([ApiTokenAuth::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Cars CRUD
Route::apiResource('cars', CarController::class);

// Patients CRUD
Route::apiResource('patients', PatientController::class);
