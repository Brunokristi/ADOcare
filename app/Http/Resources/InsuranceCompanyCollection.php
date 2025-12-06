<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InsuranceCompanyCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'items' => InsuranceCompanyResource::collection($this->collection),
        ];
    }
}
