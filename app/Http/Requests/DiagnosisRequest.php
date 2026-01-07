<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiagnosisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Adjust authorization logic if needed.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // When updating, the route-model `diagnosis` may be present.
        $diagnosisId = $this->route('diagnosis') ? $this->route('diagnosis')->id : null;

        return [
            'code' => [
                ($this->isMethod('post') ? 'required' : 'sometimes'),
                'string',
                'max:50',
                Rule::unique('diagnoses', 'code')->ignore($diagnosisId),
            ],
            'description' => [($this->isMethod('post') ? 'required' : 'sometimes'), 'string', 'max:255'],
        ];
    }
}
