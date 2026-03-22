<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy extends BasePolicy
{
    public function view(User $user, Patient $patient): bool
    {
        // Managers and admins can view any patient in their company
        if ($this->isManager($user) && $user->isInCompany($patient->branch->company_id)) {
            return true;
        }

        // A nurse can view a patient if assigned to the same branch and assigned to the patient
        if ($user->isInBranch($patient->branch_id)) {
            // check nurse assignment (patient->nurse_id or visit pivot) — fallback to allowing branch nurses to view
            return $patient->nurse_id === $user->id;
        }


        return false;
    }

    public function create(User $user, $branchId): bool
    {
        // User may create patient in a branch only when they belong to the same company
        // and are assigned to that branch (or are manager/admin)
        if (!$user->isInBranch($branchId) && !$this->isManager($user)) {
            return false;
        }

        return true;
    }

    public function update(User $user, Patient $patient): bool
    {
        // Managers/admins may update patients in same company
        if ($this->isManager($user) && $user->isInCompany($patient->branch->company_id)) {
            return true;
        }

        // Nurses may update only patients assigned to them
        if ($user->isInBranch($patient->branch_id)) {
            return $patient->nurse_id === $user->id;
        }

        return false;
    }

    public function delete(User $user, Patient $patient): bool
    {
        // Only managers/admins in the same company can delete
        return $this->isManager($user) && $user->isInCompany($patient->branch->company_id);
    }
}
