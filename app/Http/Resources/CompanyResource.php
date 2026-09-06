<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ico' => $this->ico,
            'dic' => $this->dic,
            'ic_dph' => $this->ic_dph,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'register' => $this->register,
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
            'stamp_path' => $this->stamp_path,
            'status' => $this->status,
            'subscription_tier_id' => $this->subscription_tier_id,
            'subscription_price_monthly' => $this->subscription_price_monthly,
            'subscription_users_limit_override' => $this->subscription_users_limit_override,
            'subscription_status' => $this->subscription_status,
            'subscription_started_at' => $this->subscription_started_at,
            'subscription_ends_at' => $this->subscription_ends_at,
            'subscription_notes' => $this->subscription_notes,

            'branches_count' => $this->whenCounted('branches'),
            'cars_count' => $this->whenCounted('cars'),
        ];
    }
}
