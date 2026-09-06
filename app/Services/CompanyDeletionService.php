<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDocument;
use App\Models\CarService;
use App\Models\Company;
use App\Models\CompanySubscriptionPaidMonth;
use App\Models\CompanySubscriptionPayment;
use App\Models\DekurzAiFeedback;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientPoint;
use App\Models\ReportMonth;
use App\Models\ScanSession;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a Company and everything that belongs to it. Records that support soft deletes
 * (Company, User, Patient, Document, Invoice) are soft-deleted - recoverable by a superadmin
 * if needed. Everything else is lower-value operational/config data tightly bound to a
 * branch/patient/car (points, visits, scan sessions, AI feedback, cars, report months,
 * per-company procedure prices, branches, legacy subscription payment records) and is
 * removed outright as part of the same cascade, since it has no independent value once its
 * owning Company is gone.
 */
class CompanyDeletionService
{
    public function delete(Company $company): void
    {
        DB::transaction(function () use ($company) {
            $branchIds = Branch::where('company_id', $company->id)->pluck('id');
            $patientIds = Patient::whereIn('branch_id', $branchIds)->pluck('id');
            $userIds = User::where('company_id', $company->id)->pluck('id');
            $carIds = Car::where('company_id', $company->id)->pluck('id');

            Document::whereIn('patient_id', $patientIds)->delete();
            Invoice::whereIn('user_id', $userIds)->delete();

            DekurzAiFeedback::whereIn('patient_id', $patientIds)->delete();
            ScanSession::whereIn('patient_id', $patientIds)->delete();
            Visit::whereIn('patient_id', $patientIds)->delete();
            PatientPoint::whereIn('patient_id', $patientIds)->delete();
            ReportMonth::whereIn('branch_id', $branchIds)->delete();

            CarDocument::whereIn('car_id', $carIds)->delete();
            CarService::whereIn('car_id', $carIds)->delete();
            Car::whereIn('id', $carIds)->delete();

            DB::table('procedure_company_prices')->where('company_id', $company->id)->delete();

            CompanySubscriptionPayment::where('company_id', $company->id)->delete();
            CompanySubscriptionPaidMonth::where('company_id', $company->id)->delete();

            Patient::whereIn('id', $patientIds)->delete();
            Branch::whereIn('id', $branchIds)->delete();

            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();
            User::whereIn('id', $userIds)->delete();

            $company->delete();
        });
    }
}
