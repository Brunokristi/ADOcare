<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\PatientPoint;
use App\Models\User;

class PatientPointPolicy extends BasePolicy
{
    /**
     * Determine whether the user can create a patient point for the given patient.
     */
    public function create(User $user, Patient $patient): bool
    {
        // Superadmin handled by Gate::before

        // Managers can create for any patient in their company
        if ($this->isManager($user) && $user->isInCompany($patient->branch->company_id)) {
            return true;
        }

        // Nurse may create only for patients assigned to them
        return $this->isNurseAssigned($user, $patient);
    }

    public function view(User $user, PatientPoint $point): bool
    {
        // Managers can view points for patients in their company
        if ($this->isManager($user) && $user->isInCompany($point->branch_id)) {
            return true;
        }

        // Nurse can view if assigned to the patient
        $patient = \App\Models\Patient::find($point->patient_id);
        return $patient ? $this->isNurseAssigned($user, $patient) : false;
    }

    public function update(User $user, PatientPoint $point): bool
    {
        // Managers can update points in their company
        if ($this->isManager($user) && $user->isInCompany($point->branch_id)) {
            return true;
        }

        // Nurse can update points for their patients
        $patient = \App\Models\Patient::find($point->patient_id);
        return $patient ? $this->isNurseAssigned($user, $patient) : false;
    }

    public function delete(User $user, PatientPoint $point): bool
    {
        // Only managers (company) or nurses assigned to the patient may delete
        if ($this->isManager($user) && $user->isInCompany($point->branch_id)) {
            return true;
        }

        $patient = \App\Models\Patient::find($point->patient_id);
        return $patient ? $this->isNurseAssigned($user, $patient) : false;
    }

    /**
     * Whether the user is allowed to force synchronous bulk deletes for this resource.
     * Accepts either a model instance or a class string as second parameter.
     */
    public function forceBulkDelete(User $user, $model = null): bool
    {
        // Restrict to admins and super-admins by default
        return $this->isAdmin($user);
    }
}
