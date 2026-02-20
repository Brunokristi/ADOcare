<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),

            'patient_id' => $this->patient_id,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,

            'terrain_time' => $this->terrain_time?->format('Y-m-d H:i:s'),
            'administrative_time' => $this->administrative_time?->format('Y-m-d H:i:s'),

            'time_on_location' => $this->time_on_location,
            'distance_to_location' => $this->distance_to_location,
            'time_to_location' => $this->time_to_location,

            'patient' => $this->whenLoaded('patient', function () {
                return new PatientResource($this->patient);
            }),
            'patient_exists' => $this->when($this->relationLoaded('patient') ?: $this->patient()->exists(), true),
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'user_exists' => $this->when($this->relationLoaded('user') ?: $this->user()->exists(), true),
            'branch' => $this->whenLoaded('branch', function () {
                return new BranchResource($this->branch);
            }),
            'branch_exists' => $this->when($this->relationLoaded('branch') ?: $this->branch()->exists(), true),
        ];
    }
}
