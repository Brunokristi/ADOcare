<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
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
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'representative_id' => 'nullable|exists:users,id',
            'code' => 'sometimes|required|string|max:255',
            'identificator' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'psc' => 'sometimes|required|string|max:10',
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
