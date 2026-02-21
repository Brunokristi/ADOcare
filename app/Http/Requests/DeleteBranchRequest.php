<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->hasGlobalRole('admin')) {
            return true;
        }

        if ($user->hasGlobalRole('manager')) {
            $branch = $this->route('branch');
            if ($branch && $branch->company_id == $user->company_id) {
                return true;
            }
        }

        return false;
    }

    public function rules(): array
    {
        return [];
    }
}
