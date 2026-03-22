<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityByZipRequest;
use App\Http\Requests\CitySuggestRequest;
use App\Http\Resources\CityCollection;
use App\Http\Resources\CityResource;
use App\Services\CityService;

/**
 * City-related endpoints used by autocomplete and ZIP lookup.
 */
class CityController extends Controller
{
    public function __construct(private readonly CityService $cityService)
    {
    }

    /**
     * Suggest cities by name prefix or ZIP prefix.
     */
    public function suggest(CitySuggestRequest $request)
    {
        $validated = $request->validated();
        $items = $this->cityService->suggest(
            query: (string) $validated['q'],
            limit: isset($validated['limit']) ? (int) $validated['limit'] : null,
        );

        return $this->success(new CityCollection($items), 'Mestá boli načítané');
    }

    /**
     * Return a city by exact ZIP (spaces ignored).
     */
    public function byZip(CityByZipRequest $request)
    {
        $validated = $request->validated();
        $city = $this->cityService->findByZip((string) $validated['zip']);

        return $this->success(
            $city ? new CityResource($city) : null,
            'Mesto bolo načítané'
        );
    }
}
