<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceCompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'address'     => $this->address,
            'city'        => $this->city,
            'psc'         => $this->psc,
            'ico'         => $this->ico,
            'dic'         => $this->dic,
            'ic_dph'      => $this->ic_dph,
            'register'    => $this->register,
            'code'        => $this->code,
            'branch_code' => $this->branch_code,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,

            // relations / counts if you want
            'patients_count'  => $this->whenCounted('patients'),
            'branches_count'  => $this->whenCounted('branches'),
        ];
    }
}
