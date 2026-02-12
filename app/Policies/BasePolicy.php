<?php

namespace App\Policies;

use App\Models\User;

/**
 * Common helpers for application policies.
 */
abstract class BasePolicy
{
    protected function sameCompany(User $user, int $companyId): bool
    {
        return $user->isInCompany($companyId) || $user->hasGlobalRole('manager');
    }

    protected function userInBranch(User $user, int $branchId): bool
    {
        return $user->isInBranch($branchId) || $user->hasGlobalRole('manager');
    }

    protected function isManager(User $user): bool
    {
        return $user->hasGlobalRole('manager') || $user->hasGlobalRole('admin') || $user->hasGlobalRole('super-admin');
    }

    protected function isAdmin(User $user): bool
    {
        return $user->hasGlobalRole('admin') || $user->hasGlobalRole('super-admin');
    }
}
