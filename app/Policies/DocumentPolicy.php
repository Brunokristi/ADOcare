<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy extends BasePolicy
{
    public function view(User $user, Document $document): bool
    {
        // User can view their own documents
        if ($document->user_id === $user->id) {
            return true;
        }

        // Managers/admins can view documents in their company
        if ($this->isManager($user)) {
            // Check if document belongs to a patient in their company
            if ($document->patient_id) {
                $patient = $document->patient;
                return $patient && $user->isInCompany($patient->branch->company_id);
            }
            // Check if document's branch is in their company
            if ($document->branch_id) {
                $branch = $document->branch;
                return $branch && $user->isInCompany($branch->company_id);
            }
        }

        return false;
    }

    public function update(User $user, Document $document): bool
    {
        // User can update their own documents
        if ($document->user_id === $user->id) {
            return true;
        }

        // Managers/admins can update documents in their company
        if ($this->isManager($user)) {
            // Check if document belongs to a patient in their company
            if ($document->patient_id) {
                $patient = $document->patient;
                return $patient && $user->isInCompany($patient->branch->company_id);
            }
            // Check if document's branch is in their company
            if ($document->branch_id) {
                $branch = $document->branch;
                return $branch && $user->isInCompany($branch->company_id);
            }
        }

        return false;
    }

    public function delete(User $user, Document $document): bool
    {
        // User can delete their own documents
        if ($document->user_id === $user->id) {
            return true;
        }

        // Managers/admins can delete documents in their company
        if ($this->isManager($user)) {
            // Check if document belongs to a patient in their company
            if ($document->patient_id) {
                $patient = $document->patient;
                return $patient && $user->isInCompany($patient->branch->company_id);
            }
            // Check if document's branch is in their company
            if ($document->branch_id) {
                $branch = $document->branch;
                return $branch && $user->isInCompany($branch->company_id);
            }
        }

        return false;
    }
}
