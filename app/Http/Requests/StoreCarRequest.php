<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evc' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'fuel_consumption_l_per_100km' => 'nullable|numeric|min:0|max:99.99',
            'company_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ];
    }
}
