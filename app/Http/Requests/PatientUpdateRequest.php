<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [

            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'personal_number' => 'nullable|string|max:255',
            'sex' => 'nullable|in:M,F',
            'contact' => 'nullable|string|max:255',

            'branch_id' => 'sometimes|integer|exists:branches,id',
            'nurse_id' => 'nullable|integer|exists:users,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'insurance_company_id' => 'nullable|integer|exists:insurance_companies,id',
            'country_id' => 'sometimes|required|integer|exists:countries,id',

            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:50',

            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            'reference_date' => 'nullable|date',
            'dekurz_number' => 'nullable|integer|min:1',
        ];
    }
}
