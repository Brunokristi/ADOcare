<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Patient;
use App\Models\PatientPoint;
use App\Models\User;

class StorePatientPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        // If a patient_id is provided, authorize against that patient context.
        $patientId = $this->input('patient_id');
        if (!empty($patientId)) {
            $patient = Patient::find($patientId);
            if (!$patient) {
                return false;
            }

            return $this->user()?->can('create', [PatientPoint::class, $patient]) ?? false;
        }

        // No patient_id provided: allow creation when the user has a general
        // ability to create PatientPoint entries (policy handles broader checks).
        return $this->user()?->can('create', PatientPoint::class) ?? false;
    }

    public function rules(): array
    {
        $isBranchNurse = $this->user()?->hasBranchRole((int) $this->input('branch_id'), 'nurse') ?? false;

        return [
            'date' => ['required', 'date'],
            'patient_personal_number' => ['required', 'string', 'max:255'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_id' => ['nullable', 'integer'],

            'diagnosis_code' => ['required', 'string', 'max:255'],
            'diagnosis_id' => ['nullable', 'integer'],

            'procedure_code' => ['required', 'string', 'max:255'],
            'procedure_id' => ['nullable', 'integer'],

            'doctor_pzs' => ['nullable', 'string', 'max:255'],
            'doctor_zpr' => ['nullable', 'string', 'max:255'],
            'doctor_id' => ['nullable', 'integer'],

            'reference_date' => ['required', 'date'],
            // If the authenticated user is a branch nurse, allow omitting `user_id`.
            // Managers / admins must provide the `user_id` of the nurse the point is for.
            'user_id' => [$isBranchNurse ? 'nullable' : 'required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $user = auth()->user();
            $branchId = $this->input('branch_id');
            $providedUserId = $this->input('user_id');

            if ($user && $user->hasBranchRole((int) $branchId, 'nurse')) {
                // Nurse creating: if they provided a user_id it must match their id.
                if (!empty($providedUserId) && (int) $providedUserId !== $user->id) {
                    $v->errors()->add('user_id', 'As a nurse you may not set a different user_id.');
                }
            } else {
                // Manager / admin: ensure a nurse id is provided and belongs to a nurse on the branch.
                if (empty($providedUserId)) {
                    $v->errors()->add('user_id', 'The nurse (user_id) is required when creating on behalf of others.');
                } else {
                    $target = User::find($providedUserId);
                    if (!$target || !$target->hasBranchRole((int) $branchId, 'nurse')) {
                        $v->errors()->add('user_id', 'The provided user_id must belong to a nurse assigned to the branch.');
                    }
                }
            }
        });
    }
}
