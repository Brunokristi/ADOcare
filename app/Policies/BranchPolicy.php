<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy extends BasePolicy
{
    public function view(User $user, Branch $branch): bool
    {
        // users can view a branch if they belong to same company or are manager/admin
        return $user->isInCompany($branch->company_id) || $this->isManager($user);
    }

    public function create(User $user, int $companyId): bool
    {
        // creating branches limited to company managers/admins
        return $this->isManager($user) && $user->isInCompany($companyId);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->isManager($user) && $user->isInCompany($branch->company_id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->isManager($user) && $user->isInCompany($branch->company_id);
    }
}
