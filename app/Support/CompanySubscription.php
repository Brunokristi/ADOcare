<?php

namespace App\Support;

use App\Exceptions\StudioKristianBillingException;
use App\Models\Company;
use App\Services\StudioKristianBillingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    /**
     * Drop the cached remote trial/subscription lookups for a Company. Call this right
     * after an action that changes StudioKristian's state for it (e.g. starting a trial)
     * so the next read isn't served stale cached data from before that action.
     */
    public static function forgetRemoteCache(Company $company): void
    {
        Cache::forget("studiokristian_billing:trial_state:{$company->id}");
        Cache::forget("studiokristian_billing:subscription_active:{$company->id}");
    }

    /**
     * Resolves which billing state is CURRENT for display/access purposes, given an already
     * fetched trial state and subscriptions list. An active paid subscription always wins
     * over the application trial - StudioKristian's own trial-conversion (via its Stripe
     * webhook) may not have run yet, so this must not depend on the trial's own status.
     *
     * @param  array<int, array{status?: string}>  $subscriptions
     */
    public static function resolveCurrentState(array $trial, array $subscriptions): array
    {
        $activeSubscription = collect($subscriptions)->first(
            fn ($subscription) => in_array(strtolower((string) ($subscription['status'] ?? '')), ['active', 'trialing'], true)
        );

        if ($activeSubscription) {
            return ['type' => 'subscription', 'subscription' => $activeSubscription];
        }

        if ($trial['active'] ?? false) {
            return ['type' => 'trial', 'trial' => $trial];
        }

        if ($trial['expired'] ?? false) {
            return ['type' => 'expired_trial', 'trial' => $trial];
        }

        return ['type' => 'none'];
    }

    /**
     * Cheap, local-only check ("do we already know a trial/subscription is active?") that
     * never calls StudioKristian. Intended for hot paths like starting a trial, where a
     * remote lookup is only useful to change behavior, not just to confirm what a previous
     * successful action already told us.
     */
    public static function isLocallyKnownActive(Company $company): bool
    {
        return self::localTrialState($company)['active'] || strtolower(trim((string) $company->subscription_status)) === 'active';
    }

    /**
     * Application-managed trial state. StudioKristian is authoritative once a Company has
     * a Customer Credential (it determines trial duration/credits from the SaaS Project
     * configuration - not a Stripe trial). Falls back to the local cached fields
     * (`subscription_status`/`subscription_ends_at`) when StudioKristian is unavailable or
     * no Customer Credential exists yet (e.g. still mid-onboarding).
     */
    public static function trialState(?Company $company): array
    {
        if (!$company) {
            return self::localTrialState(null);
        }

        if ($company->hasBillingCustomerToken()) {
            $remote = self::remoteTrialState($company);

            if ($remote !== null) {
                return $remote;
            }
        }

        return self::localTrialState($company);
    }

    /**
     * Same trial-state shape/fallback contract as `trialState()`, but built from trial data
     * already fetched as part of a wider billing snapshot (e.g. the `/customer/subscriptions`
     * response, which bundles trial+subscriptions+payments+invoices in one call) - avoids a
     * second, redundant `/customer/trial` request just to re-derive the same information.
     */
    public static function trialStateFromSnapshot(?Company $company, ?array $trialData): array
    {
        $mapped = self::mapRemoteTrialData($trialData);

        return $mapped ?? self::localTrialState($company);
    }

    private static function remoteTrialState(Company $company): ?array
    {
        $cacheKey = "studiokristian_billing:trial_state:{$company->id}";

        return Cache::remember($cacheKey, 30, function () use ($company) {
            try {
                $data = app(StudioKristianBillingService::class)->getTrialState($company);
            } catch (StudioKristianBillingException $e) {
                Log::warning('StudioKristian trial state unavailable, falling back to local state.', [
                    'company_id' => $company->id,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            return self::mapRemoteTrialData($data);
        });
    }

    /**
     * Shared mapping from StudioKristian's raw trial payload (whether fetched via its own
     * `/customer/trial` endpoint or bundled into the `/customer/subscriptions` snapshot) into
     * ADOCare's trial state shape. Returns null for an empty/missing payload (no trial exists).
     */
    private static function mapRemoteTrialData(?array $data): ?array
    {
        if (empty($data)) {
            return null;
        }

        $endsAt = self::parseDate($data['ends_at'] ?? null);
        $startedAt = self::parseDate($data['started_at'] ?? null);
        $status = strtolower(trim((string) ($data['status'] ?? '')));
        $active = $status === 'active';
        $expired = $status === 'expired';

        return [
            'active' => $active,
            'expired' => $expired,
            'started_at' => $startedAt?->toDateString(),
            'ends_at' => $endsAt?->toDateString(),
            'days_remaining' => $active && $endsAt !== null
                ? (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false)
                : null,
            'credits' => $data['credit_allowance'] ?? null,
            'credits_remaining' => $data['credits_remaining'] ?? null,
        ];
    }

    /**
     * Locally-cached trial state only - never calls StudioKristian. Used both as the
     * fallback in `trialState()` and as a cheap "is a trial already running/expired"
     * check right after writing fresh values from a StudioKristian response, so callers
     * don't have to make an extra remote round trip just to read back what they already know.
     */
    public static function localTrialState(?Company $company): array
    {
        $status = strtolower(trim((string) $company?->subscription_status));
        $endsAt = $company?->subscription_ends_at;
        $expired = $status === 'trial' && $endsAt !== null && $endsAt->isPast();

        return [
            'active' => $status === 'trial' && !$expired,
            'expired' => $status === 'trial' && $expired,
            'started_at' => $status === 'trial' ? optional($company?->subscription_started_at)->toDateString() : null,
            'ends_at' => $status === 'trial' ? optional($endsAt)->toDateString() : null,
            'days_remaining' => $status === 'trial' && $endsAt !== null && !$expired
                ? (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false)
                : null,
        ];
    }

    private static function parseDate(mixed $value): ?\Illuminate\Support\Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Whether the Company may use the application right now.
     *
     * An active (non-expired) application trial always wins locally. For companies that
     * already have a StudioKristian customer credential, StudioKristian is authoritative
     * for paid billing state. Companies not yet migrated fall back to the legacy local
     * paid-months record so existing customers keep working.
     */
    public static function hasActiveSubscription(?Company $company): bool
    {
        if (!$company) {
            return true;
        }

        if (self::trialState($company)['active']) {
            return true;
        }

        if ($company->hasBillingCustomerToken()) {
            return self::hasActiveRemoteSubscription($company);
        }

        return self::hasActiveLegacySubscription($company);
    }

    private static function hasActiveRemoteSubscription(Company $company): bool
    {
        $cacheKey = "studiokristian_billing:subscription_active:{$company->id}";

        return (bool) Cache::remember($cacheKey, 60, function () use ($company) {
            try {
                $subscriptions = app(StudioKristianBillingService::class)->getCustomerSubscriptions($company);
            } catch (StudioKristianBillingException $e) {
                // Fail open on upstream unavailability so a StudioKristian outage does not lock customers out.
                Log::warning('StudioKristian billing state unavailable, falling back to legacy state.', [
                    'company_id' => $company->id,
                    'message' => $e->getMessage(),
                ]);

                return self::hasActiveLegacySubscription($company);
            }

            return collect($subscriptions)->contains(function ($subscription) {
                return in_array($subscription['status'] ?? null, ['active', 'trialing'], true);
            });
        });
    }

    private static function hasActiveLegacySubscription(Company $company): bool
    {
        $status = strtolower(trim((string) $company->subscription_status));

        if ($status !== 'active') {
            return false;
        }

        $now = now();

        return $company->subscriptionPaidMonths()
            ->where('year', (int) $now->year)
            ->where('month', (int) $now->month)
            ->exists();
    }

    public static function subscriptionExpiredMessage(?Company $company): string
    {
        $companyName = $company?->name ? 'Spoločnosť ' . $company->name : 'Vaša spoločnosť';

        return $companyName . ' nemá aktívne predplatné. Obnovte ho, aby ste mohli pokračovať v používaní aplikácie.';
    }
}

