<?php

namespace App\Policies;

use App\Models\Procedure;
use App\Models\User;

class ProcedurePolicy extends BasePolicy
{
    public function view(User $user, Procedure $procedure): bool
    {
        return true;
    }

    public function delete(User $user, Procedure $procedure): bool
    {
        return $user->hasGlobalRole('superadmin');
    }
}
