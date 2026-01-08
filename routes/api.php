<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarController;
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
use App\Http\Controllers\Api\KilometersExportController;
use App\Http\Controllers\Api\DekurzController;
use App\Http\Controllers\Api\NurseDiagnosisController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProposalDocumentController;
use App\Http\Controllers\Api\AgreementDocumentController;


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
Route::middleware('auth:sanctum')->patch('/v1/users/me/last-branch', [AuthController::class, 'updateLastBranch']);


Route::prefix('v1')->middleware('api.auth')->group(function () {

    Route::apiResource('cars', CarController::class);

    Route::apiResource('patients', PatientController::class);
    Route::delete('patients', [PatientController::class, 'destroyMany']);
    Route::group(['prefix' => 'patients/{patient}'], function () {
        Route::get('insurance-company', [PatientController::class, 'insuranceCompany']);
        Route::get('doctor', [PatientController::class, 'doctor']);
        Route::get('diagnoses', [PatientController::class, 'diagnoses']);
        Route::get('procedures', [PatientController::class, 'procedures']);
        Route::get('patient-points', [PatientController::class, 'patientPoints']);

    });
    Route::apiResource('branches', BranchController::class);
    Route::get('/branches/{branch}/patients', [BranchController::class, 'patients']);
    Route::get('/branches/{branch}/doctors', [BranchController::class, 'doctors']);

    Route::apiResource('insurance-companies', InsuranceCompanyController::class);
    Route::apiResource('diagnoses', DiagnosisController::class);
    Route::apiResource('procedures', ProcedureController::class);
    Route::apiResource('nurse-diagnoses', NurseDiagnosisController::class);
    Route::apiResource('macros', MacroController::class);

    Route::apiResource('patient-points', PatientPointController::class);

    Route::get('/dekurz/dates', [DekurzController::class, 'uniqueDates']);

    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('doctors', DoctorController::class);
    Route::apiResource('report-months', ReportMonthController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('text-blocks', TextBlockController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('visits', VisitController::class);
    Route::apiResource('visit-texts', VisitTextController::class);

    Route::post('/batches/points/preview', [PointsExportController::class, 'preview']);
    Route::post('/batches/points/download', [PointsExportController::class, 'download']);
    Route::post('/batches/points/statement-pdf', [PointsExportController::class, 'statementPdf']);

    Route::post('/batches/kilometers/preview', [KilometersExportController::class, 'preview']);
    Route::post('/batches/kilometers/download', [KilometersExportController::class, 'download']);
    Route::post('/batches/kilometers/statement-pdf', [KilometersExportController::class, 'statementPdf']);

    Route::get('/geocode/autocomplete', [\App\Http\Controllers\Api\GeocodeController::class, 'autocomplete']);

    Route::post('/branches/{branch}/doctors/{doctor}', [\App\Http\Controllers\Api\BranchDoctorController::class, 'attach']);
    Route::delete('/branches/{branch}/doctors/{doctor}', [\App\Http\Controllers\Api\BranchDoctorController::class, 'detach']);

    Route::apiResource('patients/{patient}/documents', DocumentController::class);
    Route::get('patients/{patient}/documents/{type}/by-type', [DocumentController::class, 'getByType']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download']);

    Route::post('/proposals', [ProposalDocumentController::class, 'store']);
    Route::get('/proposals/{documentId}', [ProposalDocumentController::class, 'show']);
    Route::get('/patients/{patientId}/proposals', [ProposalDocumentController::class, 'getByPatient']);
    Route::get('/patients/{patientId}/proposals/latest', [ProposalDocumentController::class, 'latestByPatient']);

    Route::post('/agreements', [AgreementDocumentController::class, 'store']);
    Route::get('/agreements/{documentId}', [AgreementDocumentController::class, 'show']);
    Route::get('/patients/{patientId}/agreements', [AgreementDocumentController::class, 'getByPatient']);

    Route::post('/documents/generate-pdf', [DocumentController::class, 'generatePdf']);
});
