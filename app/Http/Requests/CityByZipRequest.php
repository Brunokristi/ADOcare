<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for city lookup by ZIP endpoint.
 */
class CityByZipRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for the request.
     */
    public function rules(): array
    {
        return [
            'zip' => ['required', 'string', 'min:3', 'max:10'],
        ];
    }
}
