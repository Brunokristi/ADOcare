<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admins may change the system role
        if ($this->user() && $this->user()->hasGlobalRole('admin')) {
            return true;
        }
        // if the client is not attempting to modify role_id we permit update
        return !$this->filled('role_id');
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
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
