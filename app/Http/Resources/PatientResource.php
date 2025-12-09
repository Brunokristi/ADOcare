<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            // columns
            'id'                 => $this->id,
            'first_name'         => $this->first_name,
            'last_name'          => $this->last_name,
            'title'              => $this->title,
            'personal_number'    => $this->personal_number,
            'sex'                => $this->sex,
            'contact'            => $this->contact,
            'doctor_id'          => $this->doctor_id,
            'insurance_company_id' => $this->insurance_company_id,
            'address'            => $this->address,
            'city'               => $this->city,
            'zip'                => $this->zip,
            'latitude'           => $this->latitude,
            'longitude'          => $this->longitude,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,

            // relations
            'doctor' => $this->whenLoaded('doctor', function () {
                return [
                    'id'         => $this->doctor?->id,
                    'first_name' => $this->doctor?->first_name,
                    'last_name'  => $this->doctor?->last_name,
                    'title'      => $this->doctor?->title,
                    'zpr'        => $this->doctor?->zpr,
                    'pzs'        => $this->doctor?->pzs,
                    'created_at' => $this->doctor?->created_at,
                    'updated_at' => $this->doctor?->updated_at,
                ];
            }),

            'insurance_company' => $this->whenLoaded('insuranceCompany', function () {
                return [
                    'id'      => $this->insuranceCompany?->id,
                    'name'    => $this->insuranceCompany?->name,
                    'address' => $this->insuranceCompany?->address,
                    'city'    => $this->insuranceCompany?->city,
                    'psc'     => $this->insuranceCompany?->psc,
                    'ico'     => $this->insuranceCompany?->ico,
                    'dic'     => $this->insuranceCompany?->dic,
                    'ic_dph'  => $this->insuranceCompany?->ic_dph,
                    'register'=> $this->insuranceCompany?->register,
                    'code'    => $this->insuranceCompany?->code,
                    'branch_code' => $this->insuranceCompany?->branch_code,
                    'created_at'  => $this->insuranceCompany?->created_at,
                    'updated_at'  => $this->insuranceCompany?->updated_at,
                ];
            }),

            'visits' => $this->whenLoaded('visits', function () {
                return $this->visits->map(function ($visit) {
                    return [
                        'id'          => $visit->id,
                        'date'        => $visit->date,
                        'examination' => $visit->examination,
                        'statement'   => $visit->statement,
                        'patient_id'  => $visit->patient_id,
                        'month_id'    => $visit->month_id,
                        'created_at'  => $visit->created_at,
                        'updated_at'  => $visit->updated_at,
                    ];
                });
            }),

            // counts + exists flags
            'visits_count'            => $this->whenCounted('visits', $this->visits_count ?? $this->visits()->count()),
            'doctor_exists'           => $this->doctor()->exists(),
            'visits_exists'           => $this->visits()->exists(),
            'insurance_company_exists'=> $this->insuranceCompany()->exists(),
        ];
    }
}
