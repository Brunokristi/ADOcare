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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($r) => ['id' => $r->id, 'position' => $r->position]);
            }),

            'role_names' => $this->role_names,

            'branches' => $this->whenLoaded('branches', function () {
                return $this->branches;
            }),
            'branch_roles' => $this->whenLoaded('branches', function () {
                return $this->branch_roles;
            }),
            'company' => $this->whenLoaded('company', function () {
                return $this->company;
            }),

        ];
    }
}
