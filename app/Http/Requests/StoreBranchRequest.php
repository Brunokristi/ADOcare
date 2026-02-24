<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->hasGlobalRole('admin')) {
            return true;
        }

        if ($user->hasGlobalRole('manager')) {
            $companyId = $this->input('company_id');
            if ($companyId && $companyId != $user->company_id) {
                return false;
            }
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:company,id',
            'representative_id' => 'nullable|exists:users,id',
            'code' => 'required|string|max:255',
            'identificator' => 'required|string|max:255',
            'address' => 'required|string',
            'city' => 'required|string',
            'psc' => 'required|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'terrain_start_time' => 'nullable|date_format:H:i',
            'administrative_start_time' => 'nullable|date_format:H:i',
            'per_location_time' => 'nullable|integer|min:0',
        ];
    }
}
