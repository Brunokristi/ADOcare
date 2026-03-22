<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for creating a leave document.
 */
class StoreLeaveDocumentRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get validation rules for the request.
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'date' => ['required', 'date'],
            'problems' => ['nullable', 'array'],
            'other_findings' => ['nullable', 'string'],
            'results' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'received' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }
}
