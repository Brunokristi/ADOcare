<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string',
            'ico' => 'nullable|string',
            'dic' => 'nullable|string',
            'ic_dph' => 'nullable|string',
            'iban' => 'nullable|string',
            'bic' => 'nullable|string',
            'register' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'psc' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'invoice_number' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'representative_id' => 'nullable|integer|exists:users,id',
            'stamp' => 'nullable|file|mimes:png|max:5120',
        ];
    }
}
