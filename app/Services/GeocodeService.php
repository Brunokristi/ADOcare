<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeocodeService
{
    public function autocomplete(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['body' => ['predictions' => []], 'status' => 200];
        }

        $r = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $text,
            'key' => config('services.google.maps_key'),
            'components' => 'country:sk',
            'language' => 'sk',
        ]);

        $json = $r->json();
        $predictions = $json['predictions'] ?? [];

        if (!empty($predictions)) {
            return ['body' => $json, 'status' => $r->status()];
        }

        if (($json['status'] ?? null) === 'REQUEST_DENIED' || empty($predictions)) {
            $fallback = $this->autocompleteFallback($text);
            if (!empty($fallback['body']['predictions'] ?? [])) {
                return $fallback;
            }
        }

        return ['body' => $json, 'status' => $r->status()];
    }

    private function autocompleteFallback(string $text): array
    {
        $r = Http::timeout(10)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => config('app.name', 'ADOcare') . '/autocomplete-fallback',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $text,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'countrycodes' => 'sk',
                'limit' => 10,
            ]);

        if (!$r->successful()) {
            return ['body' => ['predictions' => []], 'status' => $r->status()];
        }

        $predictions = collect($r->json() ?? [])
            ->map(function (array $item): array {
                $address = $item['display_name'] ?? '';
                $street = trim(implode(' ', array_filter([
                    $item['address']['road'] ?? null,
                    $item['address']['house_number'] ?? null,
                ])));
                $city = trim((string) ($item['address']['city'] ?? ($item['address']['town'] ?? ($item['address']['village'] ?? ($item['address']['municipality'] ?? '')))));
                $zip = trim((string) ($item['address']['postcode'] ?? ''));

                return [
                    'description' => $address,
                    'place_id' => 'osm:' . ($item['place_id'] ?? $item['osm_id'] ?? uniqid('', true)),
                    'source' => 'nominatim',
                    'lat' => isset($item['lat']) ? (float) $item['lat'] : null,
                    'lng' => isset($item['lon']) ? (float) $item['lon'] : null,
                    'address' => $address,
                    'street' => $street,
                    'city' => $city,
                    'zip' => $zip,
                ];
            })
            ->values()
            ->all();

        return ['body' => ['predictions' => $predictions], 'status' => 200];
    }

    public function details(string $placeId): array
    {
        $placeId = trim($placeId);
        if ($placeId === '') {
            return ['body' => ['error' => 'Missing place_id'], 'status' => 400];
        }

        $r = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'key' => config('services.google.maps_key'),
            'fields' => 'address_component,geometry,formatted_address',
            'language' => 'sk',
        ]);

        return ['body' => $r->json(), 'status' => $r->status()];
    }

    public function reverse(float $lat, float $lon): array
    {
        $r = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $lat . ',' . $lon,
            'key' => config('services.google.geocoding_key'),
            'language' => 'sk',
            'result_type' => 'street_address|premise|route',
        ]);

        $json = $r->json();
        $results = $json['results'] ?? [];
        if (empty($results)) {
            return [
                'body' => [
                    'address' => '',
                    'city' => '',
                    'postcode' => '',
                    'raw' => $json,
                ],
                'status' => $r->status()
            ];
        }

        $first = $results[0];
        $components = $first['address_components'] ?? [];
        $parsed = $this->parseAddressComponents($components);
        $formatted = trim((string) ($first['formatted_address'] ?? ''));

        return [
            'body' => [
                'address' => $formatted ?: $parsed['streetOnly'],
                'city' => $parsed['city'],
                'street' => $parsed['streetOnly'],
                'zip' => $parsed['zip'],
                'place_id' => $first['place_id'] ?? null,
                'components' => $parsed,
                'raw' => $first,
            ],
            'status' => $r->status(),
        ];
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
}
