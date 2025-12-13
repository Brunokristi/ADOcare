<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportMonthResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'month' => $this->month,
            'year' => $this->year,
            'examination_start' => $this->examination_start,
            'examination_end' => $this->examination_end,
            'statement_start' => $this->statement_start,
            'statement_end' => $this->statement_end,
            'first_day' => $this->first_day,
            'last_day' => $this->last_day,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'first_name' => $this->user?->first_name,
                    'last_name' => $this->user?->last_name,
                ];
            }),

            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch?->id,
                    'code' => $this->branch?->code,
                ];
            }),

            'visits_count' => $this->whenCounted('visits'),
        ];
    }
}
