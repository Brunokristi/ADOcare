<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
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
