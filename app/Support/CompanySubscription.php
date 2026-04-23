<?php

namespace App\Support;

use App\Models\Company;

class CompanySubscription
{
    public static function effectiveUsersLimit(?Company $company): ?int
    {
        if (!$company) {
            return null;
        }

        $override = $company->subscription_users_limit_override;
        if ($override !== null) {
            return (int) $override;
        }

        $company->loadMissing('subscriptionTier');

        if ($company->subscriptionTier?->users_limit !== null) {
            return (int) $company->subscriptionTier->users_limit;
        }

        return null;
    }

    public static function hasActiveSubscription(?Company $company): bool
    {
        if (!$company) {
            return true;
        }

        $status = strtolower(trim((string) $company->subscription_status));

        if (!in_array($status, ['active', 'trial'], true)) {
            return false;
        }

        // Trial accounts are considered active regardless of paid months.
        if ($status === 'trial') {
            return true;
        }

        $now = now();
        $year = (int) $now->year;
        $month = (int) $now->month;

        return $company->subscriptionPaidMonths()
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }

    public static function subscriptionExpiredMessage(?Company $company): string
    {
        $companyName = $company?->name ? 'Spoločnosť ' . $company->name : 'Vaša spoločnosť';

        return $companyName . ' nemá aktívne predplatné. Obnovte ho, aby ste mohli pokračovať v používaní aplikácie.';
    }
}
