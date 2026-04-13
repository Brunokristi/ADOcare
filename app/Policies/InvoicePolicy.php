<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends BasePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasGlobalRole('superadmin')) {
            return true;
        }

        if (!$user->company_id) {
            return false;
        }

        return (bool) $invoice->user()->where('company_id', $user->company_id)->exists();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
