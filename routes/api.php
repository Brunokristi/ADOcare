<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\PatientController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API routes: wrap all routes with the ForceJsonResponse alias via Kernel
// and use shorthand middleware aliases (see App\Http\Kernel.php)

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // Protected auth routes (require Bearer token)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('role:admin');
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Cars CRUD
Route::apiResource('cars', CarController::class);

// Patients CRUD
Route::apiResource('patients', PatientController::class);
