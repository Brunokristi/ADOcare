<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evc' => 'sometimes|required|string|max:255',
            'company_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ];
    }
}
