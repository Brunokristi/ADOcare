<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admins may assign a system role when creating a user.
        if ($this->user() && $this->user()->hasGlobalRole('admin')) {
            return true;
        }
        // if no role_id is provided we allow creation (default handled by seeder)
        return !$this->filled('role_id');
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'nullable|email',
            'login' => 'nullable|string',
            'pin' => 'nullable|string',
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'branches' => 'sometimes|array',
            'branches.*.branch_id' => 'required_with:branches|integer|exists:branches,id',
            'branches.*.working_time' => 'nullable|numeric|min:0|max:1',
        ];
    }
}
