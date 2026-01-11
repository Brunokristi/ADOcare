<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchDoctorController;
use App\Http\Controllers\Api\BranchPatientController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\KilometersExportController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\InsuranceCompanyController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\PatientPointController;
use App\Http\Controllers\Api\PointsExportController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ReportMonthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TextBlockController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\VisitTextController;
use App\Http\Controllers\Api\GeocodeController;
use \App\Http\Controllers\Api\MacroController;
use App\Http\Controllers\Api\DekurzController;
use App\Http\Controllers\Api\NurseDiagnosisController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProposalDocumentController;
use App\Http\Controllers\Api\AgreementDocumentController;
use App\Http\Controllers\Api\CPDocumentController;
use App\Http\Controllers\Api\DZCDocumentController;
use App\Http\Controllers\Api\DekurzDocumentController;
use App\Http\Controllers\Api\PointsExportController as PointsExportControllerAlias;
use App\Http\Controllers\Api\KilometersExportController as KilometersExportControllerAlias;



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

    Route::macro('apiResourceComplete', function ($name, $controller) {
        // register the normal resource routes
        Route::apiResource($name, $controller);
        // register a collection-level DELETE route for bulk deletions
        Route::delete($name, [$controller, 'destroyMany']);
    });


    Route::apiResourceComplete('cars', CarController::class);

    // Branch-scoped patient collection routes (explicit)
    // Route::get('/branches/{branch}/patients', [\App\Http\Controllers\Api\BranchPatientController::class, 'index']);
    // Route::post('/branches/{branch}/patients', [\App\Http\Controllers\Api\BranchPatientController::class, 'store']);
    // Route::delete('/branches/{branch}/patients', [\App\Http\Controllers\Api\BranchPatientController::class, 'destroyMany']);
    Route::apiResourceComplete('branches/{branch}/patients', BranchPatientController::class);
    Route::apiResource('patients', PatientController::class)->except(['index', 'store']);
    Route::group(['prefix' => 'patients/{patient}'], function () {
        Route::get('insurance-company', [PatientController::class, 'insuranceCompany']);
        Route::get('doctor', [PatientController::class, 'doctor']);
        Route::get('diagnoses', [PatientController::class, 'diagnoses']);
        Route::get('procedures', [PatientController::class, 'procedures']);
        Route::get('patient-points', [PatientController::class, 'patientPoints']);
        Route::get('documents', [PatientController::class, 'documents']);
    });

    Route::apiResourceComplete('insurance-companies', InsuranceCompanyController::class);

    // Newly added resources
    Route::apiResourceComplete('branches', BranchController::class);
    // (branch patients now handled by BranchPatientController)
    Route::get('/branches/{branch}/favourite-doctors', [BranchDoctorController::class, 'doctors']);
    Route::post('/branches/{branch}/favourite-doctors/{doctor}', [BranchDoctorController::class, 'attach']);
    Route::delete('/branches/{branch}/favourite-doctors/{doctor}', [BranchDoctorController::class, 'detach']);

    Route::apiResourceComplete('doctors', DoctorController::class);

    Route::apiResourceComplete('diagnoses', DiagnosisController::class);
    Route::apiResourceComplete('macros', MacroController::class);
    Route::apiResourceComplete('procedures', ProcedureController::class);
    Route::apiResourceComplete('patient-points', PatientPointController::class);
    Route::apiResourceComplete('report-months', ReportMonthController::class);
    Route::apiResourceComplete('roles', RoleController::class);
    Route::apiResourceComplete('text-blocks', TextBlockController::class);
    Route::apiResourceComplete('users', UserController::class);
    Route::apiResourceComplete('visits', VisitController::class);
    Route::apiResourceComplete('visit-texts', VisitTextController::class);

    Route::post('/batches/points/preview', [PointsExportController::class, 'preview']);
    Route::post('/batches/points/download', [PointsExportController::class, 'download']);
    Route::post('/batches/points/statement-pdf', [PointsExportController::class, 'statementPdf']);

    Route::post('/batches/kilometers/preview', [KilometersExportController::class, 'preview']);
    Route::post('/batches/kilometers/download', [KilometersExportController::class, 'download']);
    Route::post('/batches/kilometers/statement-pdf', [KilometersExportController::class, 'statementPdf']);

    Route::get('/geocode/autocomplete', [\App\Http\Controllers\Api\GeocodeController::class, 'autocomplete']);

    Route::post('/branches/{branch}/doctors/{doctor}', [\App\Http\Controllers\Api\BranchDoctorController::class, 'attach']);
    Route::delete('/branches/{branch}/doctors/{doctor}', [\App\Http\Controllers\Api\BranchDoctorController::class, 'detach']);

    Route::post('/proposals', [ProposalDocumentController::class, 'store']);
    Route::get('/proposals/{documentId}', [ProposalDocumentController::class, 'show']);
    Route::get('/patients/{patientId}/proposals', [ProposalDocumentController::class, 'getByPatient']);
    Route::get('/patients/{patientId}/proposals/latest', [ProposalDocumentController::class, 'latestByPatient']);

    Route::post('/agreements', [AgreementDocumentController::class, 'store']);
    Route::get('/agreements/{documentId}', [AgreementDocumentController::class, 'show']);
    Route::get('/patients/{patientId}/agreements', [AgreementDocumentController::class, 'getByPatient']);

    Route::post('/cps', [CPDocumentController::class, 'store']);
    Route::get('/cps/{documentId}', [CPDocumentController::class, 'show']);

    Route::post('/dzcs', [DZCDocumentController::class, 'store']);
    Route::get('/dzcs/{documentId}', [DZCDocumentController::class, 'show']);

    Route::post('/dekurz', [DekurzDocumentController::class, 'store']);
    Route::get('/dekurz/{documentId}', [DekurzDocumentController::class, 'show']);

    Route::post('/documents/generate-pdf', [DocumentController::class, 'generatePdf']);
    Route::delete('/documents', [DocumentController::class, 'destroyMany']);
});
