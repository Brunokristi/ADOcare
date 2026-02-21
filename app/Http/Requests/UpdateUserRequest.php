<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
