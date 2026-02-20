<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'title' => $this->title,
            'zpr' => $this->zpr ?? null,
            'pzs' => $this->pzs ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'patients' => $this->whenLoaded('patients', function () {
                return $this->patients ? PatientResource::collection($this->patients) : null;
            }),
            'patients_count' => $this->whenCounted('patients', $this->patients_count ?? $this->patients()->count()),
            'patients_exists' => $this->when($this->relationLoaded('patients') ?: $this->patients()->exists(), true),

            'assigned_patients' => $this->whenLoaded('assigned_patients', function () {
                return $this->assigned_patients ? PatientResource::collection($this->assigned_patients) : null;
            }),
            'assigned_patients_count' => $this->whenCounted('assigned_patients', $this->assigned_patients_count ?? $this->assigned_patients()->count()),
            'assigned_patients_exists' => $this->when($this->relationLoaded('assigned_patients') ?: $this->assigned_patients()->exists(), true),

            'assigned_branches' => $this->whenLoaded('assigned_branches', function () {
                return $this->assigned_branches ? BranchResource::collection($this->assigned_branches) : null;
            }),
            'assigned_branches_count' => $this->whenCounted('assigned_branches', $this->assigned_branches_count ?? $this->assigned_branches()->count()),
            'assigned_branches_exists' => $this->when($this->relationLoaded('assigned_branches') ?: $this->assigned_branches()->exists(), true),
        ];
    }
}
