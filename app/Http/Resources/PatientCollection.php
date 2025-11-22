<?php

namespace App\Http\Resources;

class PatientCollection extends BaseCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    // Use the generic BaseCollection behavior and specify the Resource class.
    protected ?string $resourceClass = PatientResource::class;
}
