<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\InsuranceCompanyController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\PatientPointController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('api.auth');

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['api.auth'])->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('role:admin');
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
    });
});

Route::prefix('v1')->middleware('api.auth')->group(function () {

    Route::apiResource('cars', CarController::class);
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('insurance-companies', InsuranceCompanyController::class);
    Route::apiResource('diagnoses', DiagnosisController::class);
    Route::apiResource('procedures', ProcedureController::class);
    Route::apiResource('patient-points', PatientPointController::class);

});

