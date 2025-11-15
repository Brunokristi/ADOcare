<?php

namespace App\Http\Resources;

class CarCollection extends BaseCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    // `$resourceClass` is provided by BaseCollection; we set it here so items are
    // automatically wrapped with `CarResource`.
    protected ?string $resourceClass = CarResource::class;
}
