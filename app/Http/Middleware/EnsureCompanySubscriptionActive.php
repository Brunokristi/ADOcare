<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Support\CompanySubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCompanySubscriptionActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || $user->hasGlobalRole('superadmin')) {
            return $next($request);
        }

        $company = $user->company;

        // A Company still going through onboarding has not been billed yet by design -
        // it must not be blocked by a paid-subscription check before it can even reach
        // /onboarding/billing to provision StudioKristian and start its trial.
        if ($company?->isOnboarding()) {
            return $next($request);
        }

        if (CompanySubscription::hasActiveSubscription($company)) {
            return $next($request);
        }

        return $this->error(
            CompanySubscription::subscriptionExpiredMessage($company),
            402,
            ['subscription_expired' => true]
        );
    }
}