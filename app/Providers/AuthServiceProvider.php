<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Document;
use App\Models\InsuranceCompany;
use App\Models\Patient;
use App\Models\PatientPoint;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PatientPointPolicy;
use App\Policies\UserPolicy;
use App\Policies\InsuranceCompanyPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Patient::class => PatientPolicy::class,
        PatientPoint::class => PatientPointPolicy::class,
        Branch::class => BranchPolicy::class,
        Company::class => CompanyPolicy::class,
        User::class => UserPolicy::class,
        Document::class => DocumentPolicy::class,
        InsuranceCompany::class => InsuranceCompanyPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user) {
            if ($user->hasGlobalRole('superadmin')) {
                return true;
            }

            return null;
        });

        // Example gates for convenience
        Gate::define('manage-company', function (User $user, $companyId) {
            return $user->hasGlobalRole('superadmin') || ($user->hasGlobalRole('manager') && $user->isInCompany($companyId));
        });
    }
}
