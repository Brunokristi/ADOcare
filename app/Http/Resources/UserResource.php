<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'title' => $this->title,
            'phone_number' => $this->phone_number,
            'initials' => $this->initials,
            'login' => $this->login,
            'code' => $this->code,
            'company_id' => $this->company_id,
            'role_id' => $this->role_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'role' => $this->whenLoaded('role', function () {
                return new RoleResource($this->role);
            }),

            'branches' => $this->whenLoaded('branches', function () {
                return BranchResource::collection($this->branches);
            }),
            'branches_count' => $this->whenCounted('branches'),
            'branches_exists' => $this->when($this->relationLoaded('branches') ?: $this->branches()->exists(), true),
            'branch_roles' => $this->whenLoaded('branches', function () {
                return $this->branch_roles;
            }),
            'company' => $this->whenLoaded('company', function () {
                return new CompanyResource($this->company);
            }),
            'company_exists' => $this->when($this->relationLoaded('company') ?: $this->company()->exists(), true),

        ];
    }
}
