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