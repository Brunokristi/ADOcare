<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'examination' => $this->examination,
            'statement' => $this->statement,
            'patient_id' => $this->patient_id,
            'month_id' => $this->month_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient?->id,
                    'first_name' => $this->patient?->first_name,
                    'last_name' => $this->patient?->last_name,
                ];
            }),

            'month' => $this->whenLoaded('month', function () {
                return [
                    'id' => $this->month?->id,
                    'month' => $this->month?->month,
                    'year' => $this->month?->year,
                ];
            }),

            'texts' => $this->whenLoaded('texts', function () {
                return $this->texts->map(fn($t) => ['id' => $t->id, 'text' => $t->text, 'position' => $t->position]);
            }),
        ];
    }
}
