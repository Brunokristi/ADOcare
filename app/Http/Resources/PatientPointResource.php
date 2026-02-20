<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientPointResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'patient_personal_number' => $this->patient_personal_number,
            'patient_name' => $this->patient_name,
            'patient_id' => $this->patient_id,
            'diagnosis_code' => $this->diagnosis_code,
            'diagnosis_id' => $this->diagnosis_id,
            'procedure_code' => $this->procedure_code,
            'procedure_id' => $this->procedure_id,
            'doctor_pzs' => $this->doctor_pzs,
            'doctor_zpr' => $this->doctor_zpr,
            'doctor_id' => $this->doctor_id,
            'reference_date' => $this->reference_date,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,
            'quantity' => $this->quantity,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'patient' => $this->whenLoaded('patient', function () {
                return new PatientResource($this->patient);
            }),
            'patient_exists' => $this->when($this->relationLoaded('patient') ?: $this->patient()->exists(), true),

            'doctor' => $this->whenLoaded('doctor', function () {
                return new DoctorResource($this->doctor);
            }),
            'doctor_exists' => $this->when($this->relationLoaded('doctor') ?: $this->doctor()->exists(), true),
        ];
    }
}
