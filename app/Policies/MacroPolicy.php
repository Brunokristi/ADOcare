<?php

namespace App\Policies;

use App\Models\Macro;
use App\Models\User;

class MacroPolicy extends BasePolicy
{
    public function view(User $user, Macro $macro): bool
    {
        return (int) $macro->user_id === (int) $user->id;
    }

    public function delete(User $user, Macro $macro): bool
    {
        return $this->view($user, $macro);
    }
}
