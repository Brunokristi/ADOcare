<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function view(User $actor, User $user): bool
    {
        // Users can view themselves; managers/admins can view users within same company
        if ($actor->id === $user->id)
            return true;
        return $this->isManager($actor) && $actor->isInCompany($user->company_id);
    }

    public function update(User $actor, User $user): bool
    {
        // Users may update their own profile; managers may update users in same company
        if ($actor->id === $user->id)
            return true;
        return $this->isManager($actor) && $actor->isInCompany($user->company_id);
    }

    public function assignRole(User $actor, User $user): bool
    {
        // Only managers/admins (company-level) can assign roles to users in their company
        return $this->isManager($actor) && $actor->isInCompany($user->company_id);
    }
}
