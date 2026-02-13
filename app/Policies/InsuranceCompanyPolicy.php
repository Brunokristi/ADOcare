<?php

namespace App\Policies;

use App\Models\InsuranceCompany;
use App\Models\User;

class InsuranceCompanyPolicy extends BasePolicy
{
    public function update(User $user): bool
    {
        // Only super-admin / admin can change insurance companies
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user): bool
    {
        return $this->isAdmin($user);
    }
}
