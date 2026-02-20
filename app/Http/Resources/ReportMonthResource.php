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
                return new UserResource($this->user);
            }),

            'branch' => $this->whenLoaded('branch', function () {
                return new BranchResource($this->branch);
            }),

            'visits_count' => $this->whenCounted('visits'),
            'visits_exists' => $this->when($this->relationLoaded('visits') ?: $this->visits()->exists(), true),
        ];
    }
}
