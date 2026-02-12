<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy extends BasePolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->isInCompany($company->id) || $this->isManager($user);
    }

    public function update(User $user, Company $company): bool
    {
        // Company-level updates allowed only for admins/managers (company admins)
        return $this->isAdmin($user) || ($this->isManager($user) && $user->isInCompany($company->id));
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->isAdmin($user);
    }
}
