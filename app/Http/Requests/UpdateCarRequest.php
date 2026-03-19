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
            'model' => 'nullable|string|max:255',
            'fuel_consumption_l_per_100km' => 'nullable|numeric|min:0|max:99.99',
            'company_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ];
    }
}
