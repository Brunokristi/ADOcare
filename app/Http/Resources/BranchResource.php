<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'identificator' => $this->identificator,
            'address' => $this->address,
            'city' => $this->city,
            'psc' => $this->psc,
            'phone' => $this->phone,
            'email' => $this->email,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'representative_id' => $this->representative_id,
            'terrain_start_time' => $this->terrain_start_time,
            'administrative_start_time' => $this->administrative_start_time,

            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company?->id,
                    'name' => $this->company?->name,
                ];
            }),

            'users_count' => $this->whenCounted('users'),
            'report_months_count' => $this->whenCounted('reportMonths'),
        ];
    }
}
