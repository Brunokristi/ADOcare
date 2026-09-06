<?php

namespace App\Services;

use App\Models\Company;
use App\Support\CompanySubscription;
use Illuminate\Validation\ValidationException;

/**
 * Drives the post-registration setup checklist. Steps are derived from real Company
 * state wherever practical (per-step `complete` callbacks) so the checklist can't drift
 * from what the Company has actually configured. Add new steps here only - the frontend
 * renders whatever this returns.
 */
class OnboardingService
{
    /**
     * @return array<int, array{slug: string, label: string, complete: bool}>
     */
    public function steps(Company $company): array
    {
        $company->loadMissing('branches');

        return [
            [
                'slug' => 'company',
                'label' => 'Údaje o spoločnosti',
                'complete' => filled($company->ico) && filled($company->dic) && filled($company->address)
                    && filled($company->city) && filled($company->psc),
            ],
            [
                'slug' => 'billing',
                'label' => 'Fakturácia',
                'complete' => $company->hasBillingCustomerToken() && (
                    CompanySubscription::trialState($company)['active']
                    || CompanySubscription::hasActiveSubscription($company)
                ),
            ],
            [
                'slug' => 'branch',
                'label' => 'Pobočka',
                'complete' => $company->branches->isNotEmpty(),
            ],
        ];
    }

    public function isComplete(Company $company): bool
    {
        return collect($this->steps($company))->every(fn (array $step) => $step['complete']);
    }

    /**
     * @return array<int, string> labels of steps that are still incomplete
     */
    public function missingSteps(Company $company): array
    {
        return collect($this->steps($company))
            ->reject(fn (array $step) => $step['complete'])
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * Marks the Company as fully onboarded once every step is complete.
     *
     * @throws ValidationException when required steps are still missing
     */
    public function complete(Company $company): Company
    {
        $missing = $this->missingSteps($company);

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'onboarding' => ['Nastavenie ešte nie je kompletné: ' . implode(', ', $missing) . '.'],
            ]);
        }

        $company->update(['status' => 'active']);

        return $company->fresh();
    }
}
