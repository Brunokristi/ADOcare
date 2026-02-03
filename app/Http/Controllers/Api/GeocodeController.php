<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodeController extends Controller
{
    private function key(): string
    {
        return (string) config('services.google.maps_key');
    }

    private function normalizeZip(?string $zip): string
    {
        $zip = (string) $zip;
        return preg_replace('/\s+/', '', trim($zip)) ?: '';
    }

    private function pickComponent(array $components, string $type): string
    {
        foreach ($components as $c) {
            $types = $c['types'] ?? [];
            if (in_array($type, $types, true)) {
                return trim((string) ($c['long_name'] ?? ''));
            }
        }
        return '';
    }

    private function parseAddressComponents(array $components): array
    {
        $streetNumber = $this->pickComponent($components, 'street_number');
        $route = $this->pickComponent($components, 'route');

        // City comes back differently depending on area
        $locality = $this->pickComponent($components, 'locality');
        $postalTown = $this->pickComponent($components, 'postal_town');
        $admin2 = $this->pickComponent($components, 'administrative_area_level_2');

        $city = trim($locality ?: ($postalTown ?: $admin2));

        $zip = $this->normalizeZip($this->pickComponent($components, 'postal_code'));

        $streetOnly = trim(implode(' ', array_filter([$route, $streetNumber])));

        return [
            'streetOnly' => $streetOnly,
            'city' => $city,
            'zip' => $zip,
        ];
    }


    public function autocomplete(Request $request)
    {
        $text = trim((string) $request->query('text'));
        if ($text === '') return response()->json(['predictions' => []]);

        $r = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $text,
            'key' => config('services.google.maps_key'),
            'components' => 'country:sk',
            'language' => 'sk',
        ]);

        return response()->json($r->json(), $r->status());
    }

    public function details(Request $request)
    {
        $placeId = trim((string) $request->query('place_id'));
        if ($placeId === '') {
            return response()->json(['error' => 'Missing place_id'], 400);
        }

        $r = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'key' => config('services.google.maps_key'),
            'fields' => 'address_component,geometry,formatted_address',
            'language' => 'sk',
        ]);

        return response()->json($r->json(), $r->status());
    }
}
