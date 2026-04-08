<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Patient;

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

    /**
     * Check whether the given user is a nurse assigned to the given patient.
     * This ensures the user has the nurse role on the patient's branch and
     * that the patient record is assigned to that nurse.
     */
    protected function isNurseAssigned(User $user, Patient $patient): bool
    {
        if (!$patient || !$patient->branch_id) {
            return false;
        }

        // ensure user has branch-level nurse role
        if (!$user->hasBranchRole($patient->branch_id, 'nurse')) {
            return false;
        }

        // and the patient must be assigned to this nurse explicitly
        if ($patient->nurse_id === null || $user->id === null) {
            return false;
        }

        return (int) $patient->nurse_id === (int) $user->id;
    }
}
