<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    public function autocomplete(Request $request)
    {
        $text = trim((string) $request->query('text'));
        if ($text === '') {
            return response()->json(['features' => []]);
        }

        $response = Http::withHeaders([
            'Authorization' => config('services.ors.key'),
            'Accept' => 'application/json',
        ])->get('https://api.openrouteservice.org/geocode/autocomplete', [
                    'text' => $text,
                    'boundary.country' => 'SVK',
                    'size' => 8,
                ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }

    public function reverse(Request $request)
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');

        if (!$lat || !$lon) {
            return response()->json(['error' => 'Missing lat or lon parameter'], 400);
        }

        $response = Http::withHeaders([
            'Authorization' => config('services.ors.key'),
            'Accept' => 'application/json',
        ])->get('https://api.openrouteservice.org/geocode/reverse', [
                    'point.lat' => $lat,
                    'point.lon' => $lon,
                ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }
}
