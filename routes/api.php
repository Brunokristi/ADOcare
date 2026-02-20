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
use App\Http\Controllers\Api\MyCompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ReportMonthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TextBlockController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GeocodeController;
use \App\Http\Controllers\Api\MacroController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\DekurzController;
use App\Http\Controllers\Api\NurseDiagnosisController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProposalDocumentController;
use App\Http\Controllers\Api\AgreementDocumentController;
use App\Http\Controllers\Api\CPDocumentController;
use App\Http\Controllers\Api\DZCDocumentController;
use App\Http\Controllers\Api\DekurzDocumentController;
use App\Http\Controllers\Api\LeaveDocumentController;
use App\Http\Controllers\Api\RecordDocumentController;
use App\Http\Controllers\Api\PointsExportController as PointsExportControllerAlias;
use App\Http\Controllers\Api\KilometersExportController as KilometersExportControllerAlias;
use App\Http\Controllers\Api\VisitsController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\TotalsController;



use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\BugReportController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('api.auth');

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
Route::post('/bug-report', [BugReportController::class, 'store']);

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

    Route::apiResource('patients', PatientController::class)->except(['index', 'store']);
    Route::delete('patients', [PatientController::class, 'destroyMany']);

    Route::group(['prefix' => 'patients/{patient}'], function () {
        Route::get('insurance-company', [PatientController::class, 'insuranceCompany']);
        Route::get('doctor', [PatientController::class, 'doctor']);
        Route::get('diagnoses', [PatientController::class, 'diagnoses']);
        Route::get('procedures', [PatientController::class, 'procedures']);
        Route::get('patient-points', [PatientController::class, 'patientPoints']);
        Route::get('documents', [PatientController::class, 'documents']);
    });

    Route::apiResourceComplete('insurance-companies', InsuranceCompanyController::class);

    Route::apiResourceComplete('branches', BranchController::class);
    Route::delete('branches/delete-many', [BranchController::class, 'destroyMany']);
    Route::get('branches/{branch}/patients', [BranchPatientController::class, 'index']);
    Route::post('branches/{branch}/patients', [BranchPatientController::class, 'store']);
    Route::get('branches/{branch}/nurses', [BranchController::class, 'nurses']);
    Route::get('/branches/{branch}/favourite-doctors', [BranchDoctorController::class, 'doctors']);
    Route::post('/branches/{branch}/favourite-doctors/{doctor}', [BranchDoctorController::class, 'attach']);
    Route::delete('/branches/{branch}/favourite-doctors/{doctor}', [BranchDoctorController::class, 'detach']);

    Route::get('/my-company/branches', [MyCompanyController::class, 'branches']);
    Route::get('/my-company', [MyCompanyController::class, 'show']);
    Route::get('/my-company/cars', [MyCompanyController::class, 'cars']);
    Route::get('/my-company/users', [MyCompanyController::class, 'users']);
    Route::get('/my-company/doctors', [MyCompanyController::class, 'doctors']);

    Route::group(['prefix' => 'manager'], function () {
        Route::get('/user-statistics', [ManagerController::class, 'userStatistics']);
        Route::get('/doctor-statistics', [ManagerController::class, 'doctorStatistics']);
        Route::get('/user-totals', [ManagerController::class, 'userTotals']);
        Route::get('/branch-statistics', [ManagerController::class, 'branchStatistics']);
        Route::get('/branch-totals', [ManagerController::class, 'branchTotals']);
        Route::get('/user-totals-aggregated', [ManagerController::class, 'userTotalsAggregated']);
    });

    Route::apiResourceComplete('totals', TotalsController::class);

    Route::apiResourceComplete('doctors', DoctorController::class);
    Route::apiResourceComplete('diagnoses', DiagnosisController::class);
    Route::apiResourceComplete('nurse-diagnoses', NurseDiagnosisController::class);
    Route::apiResourceComplete('macros', MacroController::class);
    Route::get('/plans', [PlanController::class, 'index']);
    Route::apiResourceComplete('procedures', ProcedureController::class);
    Route::apiResourceComplete('patient-points', PatientPointController::class);
    Route::apiResourceComplete('report-months', ReportMonthController::class);
    Route::group(['prefix' => 'roles'], function () {
        Route::get('/branch', [RoleController::class, 'branchRoles']);
        Route::get('/company', [RoleController::class, 'companyRoles']);
        Route::get('/all', [RoleController::class, 'globalRoles']);
    });
    Route::apiResourceComplete('roles', RoleController::class);

    Route::apiResourceComplete('text-blocks', TextBlockController::class);
    Route::apiResourceComplete('users', UserController::class);
    Route::apiResourceComplete('companies', CompanyController::class);



    Route::post('/batches/points/preview', [PointsExportController::class, 'preview']);
    Route::post('/batches/points/download', [PointsExportController::class, 'download']);
    Route::post('/batches/points/statement-pdf', [PointsExportController::class, 'statementPdf']);

    Route::post('/batches/kilometers/preview', [KilometersExportController::class, 'preview']);
    Route::post('/batches/kilometers/download', [KilometersExportController::class, 'download']);
    Route::post('/batches/kilometers/statement-pdf', [KilometersExportController::class, 'statementPdf']);

    Route::get('/geocode/autocomplete', [GeocodeController::class, 'autocomplete']);
    Route::get('/geocode/details', [GeocodeController::class, 'details']);
    Route::get('/geocode/reverse', [GeocodeController::class, 'reverse']);

    Route::get('/companies/{company}/patients', [CompanyController::class, 'patients']);

    Route::post('/branches/{branch}/doctors/{doctor}', [BranchDoctorController::class, 'attach']);
    Route::delete('/branches/{branch}/doctors/{doctor}', [BranchDoctorController::class, 'detach']);

    Route::post('/proposals', [ProposalDocumentController::class, 'store']);
    Route::get('/proposals/{document}', [ProposalDocumentController::class, 'show']);
    Route::get('/patients/{patient}/proposals', [ProposalDocumentController::class, 'getByPatient']);
    Route::get('/patients/{patient}/proposals/latest', [ProposalDocumentController::class, 'latestByPatient']);

    Route::post('/agreements', [AgreementDocumentController::class, 'store']);
    Route::get('/agreements/{document}', [AgreementDocumentController::class, 'show']);

    Route::get('/cps', [CPDocumentController::class, 'index']);
    Route::post('/cps', [CPDocumentController::class, 'store']);
    Route::get('/cps/{document}', [CPDocumentController::class, 'show']);

    Route::get('/dzcs', [DZCDocumentController::class, 'index']);
    Route::post('/dzcs', [DZCDocumentController::class, 'store']);
    Route::get('/dzcs/{document}', [DZCDocumentController::class, 'show']);
    Route::get('/dzcs/{document}/csv', [DZCDocumentController::class, 'exportCsv']);

    Route::post('/dekurz', [DekurzDocumentController::class, 'store']);
    Route::get('/dekurz/available-dates', [DekurzDocumentController::class, 'availableDates']);
    Route::get('/dekurz/last', [DekurzDocumentController::class, 'last']);
    Route::get('/dekurz/{document}', [DekurzDocumentController::class, 'show']);

    Route::post('/leave-documents', [LeaveDocumentController::class, 'store']);
    Route::get('/leave-documents/{documentId}', [LeaveDocumentController::class, 'show']);
    Route::get('/patients/{patientId}/leave/latest', [LeaveDocumentController::class, 'latestByPatient']);

    Route::post('/records', [RecordDocumentController::class, 'store']);
    Route::get('/records/{document}', [RecordDocumentController::class, 'show']);
    Route::get('/patients/{patientId}/records/latest', [RecordDocumentController::class, 'latestByPatient']);

    Route::post('/documents/generate-pdf', [DocumentController::class, 'generatePdf']);
    Route::delete('/documents', [DocumentController::class, 'destroyMany']);
    Route::get('/documents/travel', [DocumentController::class, 'indexTravelDocuments']);

    Route::post('/visits/timeline', [VisitsController::class, 'monthTimeline']);
    Route::get('/visits/timeline/status', [VisitsController::class, 'checkCalculationStatus']);
    Route::get('visits/patient-time', [VisitsController::class, 'patientTimeForDay']);
    Route::get('visits/day-totals', [VisitsController::class, 'dayTotals']);
    Route::get('visits/month-totals', [VisitsController::class, 'monthTotals']);

    Route::get('/cities/suggest', [CityController::class, 'suggest']);
    Route::get('/cities/by-zip', [CityController::class, 'byZip']);
});
