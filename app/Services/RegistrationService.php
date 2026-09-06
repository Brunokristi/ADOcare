<?php

namespace App\Services;

use App\Exceptions\StudioKristianBillingException;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Public registration: creates the initial Manager User + Company together.
 * Reuses the exact same Company/User/Role domain model that the Superadmin
 * Company-creation flow produces - there is no separate "registration company" type.
 */
class RegistrationService
{
    public function __construct(private StudioKristianBillingService $billing)
    {
    }

    /**
     * @return array{user: User, company: Company}
     */
    public function register(array $data): array
    {
        $managerRoleId = Role::where('position', 'manager')->value('id');

        if (!$managerRoleId) {
            throw ValidationException::withMessages([
                'company_name' => ['Registrácia momentálne nie je možná (chýba rola manažéra).'],
            ]);
        }

        [$user, $company] = DB::transaction(function () use ($data, $managerRoleId) {
            $company = Company::create([
                'name' => $data['company_name'],
                'status' => 'onboarding',
                // No trial/subscription yet - avoid the misleading 'active' column default
                // (which would otherwise look like a real, unpaid "active" billing state).
                'subscription_status' => null,
            ]);

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'login' => $data['email'],
                'pin' => Hash::make($data['pin']),
                'company_id' => $company->id,
                'role_id' => $managerRoleId,
            ]);

            $company->update(['representative_id' => $user->id]);

            return [$user, $company];
        });

        $this->tryProvisionBillingCustomer($company);

        return ['user' => $user->fresh()->load(['role', 'company']), 'company' => $company->fresh()];
    }

    /**
     * Best-effort - a StudioKristian outage must not block registration itself.
     * The onboarding billing step can retry this later.
     */
    public function tryProvisionBillingCustomer(Company $company): bool
    {
        if ($company->hasBillingCustomerToken()) {
            return true;
        }

        try {
            $token = $this->billing->provisionCustomerCredential($company);
            $company->update(['studiokristian_customer_token' => $token]);

            return true;
        } catch (StudioKristianBillingException $e) {
            Log::warning('Could not auto-provision StudioKristian customer credential during registration.', [
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
