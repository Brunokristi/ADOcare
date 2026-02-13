<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Patient;

class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patientId = $this->input('patient_id');
        if (! $patientId) return false;

        $patient = Patient::find($patientId);
        if (! $patient) return false;

        // allow when user can create a patient in that branch (nurse in same branch or manager/admin)
        return $this->user()->isInBranch($patient->branch_id) || $this->user()->hasGlobalRole('manager');
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'medical_diagnosis_ids' => 'nullable|array',
            'medical_diagnosis_ids.*' => 'nullable|exists:diagnoses,id',
            'nurse_diagnosis_ids' => 'nullable|array',
            'nurse_diagnosis_ids.*' => 'nullable|exists:nurse_diagnoses,id',
            'date' => 'required|date',
            'epicrisis_description' => 'required|string',
            'care_plan' => 'required|string',
            'patient_mobility' => 'nullable|array',
            'expected_duration' => 'required|string',
            'procedures' => 'nullable|array',
            'procedures.*.procedure_id' => 'nullable|exists:procedures,id',
            'procedures.*.frequency' => 'nullable|string',
        ];
    }
}
