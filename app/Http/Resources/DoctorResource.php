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

            'patients_count' => $this->whenCounted('patients'),
        ];
    }
}
