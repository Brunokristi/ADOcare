<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
}
