<?php

namespace App\Http\Requests;

use App\Models\Patient;
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
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $patientId = (int) $this->input('patient_id');
        if ($patientId <= 0) {
            return false;
        }

        $patient = Patient::find($patientId);
        if (!$patient) {
            return false;
        }

        if (!$user->can('view', $patient)) {
            return false;
        }

        $branchId = $this->input('branch_id');
        if ($branchId !== null && (int) $branchId !== (int) $patient->branch_id) {
            return false;
        }

        return true;
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
