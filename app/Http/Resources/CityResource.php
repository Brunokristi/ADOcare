<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * City API resource.
 */
class CityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $zip = $this->zip;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'zip' => $zip,
            'label' => trim($this->name . ($zip ? ', ' . $zip : '')),
        ];
    }
}
