<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanySubscription;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function create(array $data): User
    {
        // only administrators may set the global/system role
        if (isset($data['role_id']) && !auth()->user()?->hasGlobalRole('admin')) {
            unset($data['role_id']);
        }

        // Don't manually hash - let the model's hashed cast handle it
        // The 'pin' cast in User model will automatically hash the value

        $companyId = $data['company_id'] ?? null;
        if (!$companyId) {
            $currentUser = auth()->user();
            $data['company_id'] = $currentUser->company_id;
            $companyId = $data['company_id'];
        }

        $this->assertUserLimitNotExceeded((int) $companyId);

        $branches = $data['branches'] ?? null;
        unset($data['branches']);

        $user = User::create($data);

        if ($branches && is_array($branches)) {
            $sync = [];
            foreach ($branches as $b) {
                if (empty($b['branch_id'])) continue;

                $sync[(int) $b['branch_id']] = [
                    'working_time' => $b['working_time'] ?? null,
                    'role_id' => $b['role_id'] ?? null,
                ];
            }
            if (!empty($sync)) $user->branches()->sync($sync);
        }

        return $user->fresh()->load(['branches', 'role']);
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['role_id']) && !auth()->user()?->hasGlobalRole('admin')) {
            unset($data['role_id']);
        }

        // Don't manually hash - let the model's hashed cast handle it
        // The 'pin' cast in User model will automatically hash the value

        $branches = $data['branches'] ?? null;
        unset($data['branches']);

        $targetCompanyId = (int) ($data['company_id'] ?? $user->company_id);
        if ($targetCompanyId !== (int) $user->company_id) {
            $this->assertUserLimitNotExceeded($targetCompanyId, $user->id);
        }

        $user->update($data);

        if ($branches && is_array($branches)) {
            $sync = [];
            foreach ($branches as $b) {
                if (empty($b['branch_id'])) continue;

                $sync[(int) $b['branch_id']] = [
                    'working_time' => $b['working_time'] ?? null,
                    'role_id' => $b['role_id'] ?? null,
                ];
            }
            $user->branches()->sync($sync);
        }

        return $user->fresh()->load(['branches', 'role']);
    }

    private function assertUserLimitNotExceeded(int $companyId, ?int $ignoreUserId = null): void
    {
        $company = Company::query()->with('subscriptionTier')->find($companyId);
        $limit = CompanySubscription::effectiveUsersLimit($company);

        if ($limit === null) {
            return;
        }

        $usersQuery = User::query()->where('company_id', $companyId);
        if ($ignoreUserId) {
            $usersQuery->where('id', '!=', $ignoreUserId);
        }

        $currentCount = (int) $usersQuery->count();

        if ($currentCount >= $limit) {
            throw ValidationException::withMessages([
                'company_id' => [
                    "Spoločnosť dosiahla limit používateľov pre aktuálne predplatné ({$limit}).",
                ],
            ]);
        }
    }
}