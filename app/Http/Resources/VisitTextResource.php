<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VisitTextResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'visit_id' => $this->visit_id,
            'text_id' => $this->text_id,

            'text' => $this->whenLoaded('text', function () {
                return [
                    'id' => $this->text?->id,
                    'text' => $this->text?->text,
                ];
            }),
        ];
    }
}
