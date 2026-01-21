<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function suggest(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:25',
        ]);

        $q = trim($data['q']);
        $limit = (int)($data['limit'] ?? 10);

        // normalize query
        $qNoSpaces = preg_replace('/\s+/', '', $q);
        $looksLikeZip = preg_match('/^\d+$/', $qNoSpaces) === 1;

        $query = City::query();

        if ($looksLikeZip) {
            // ZIP search (no accents needed)
            $query->whereRaw(
                "regexp_replace(zip, '\\s+', '', 'g') LIKE ?",
                [$qNoSpaces . '%']
            );
        } else {
            // ✅ UNACCENT + ILIKE search
            $query->whereRaw(
                "unaccent(lower(name)) LIKE unaccent(lower(?))",
                [$q . '%']
            );
        }

        $items = $query
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'zip'])
            ->map(fn (City $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'zip' => $c->zip,
                'label' => trim($c->name . ($c->zip ? ', ' . $c->zip : '')),
            ]);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function byZip(Request $request)
    {
        $data = $request->validate([
            'zip' => 'required|string|min:3|max:10',
        ]);

        $zip = preg_replace('/\s+/', '', trim($data['zip']));

        $city = City::query()
            ->whereRaw(
                "regexp_replace(zip, '\\s+', '', 'g') = ?",
                [$zip]
            )
            ->orderBy('name')
            ->first(['id', 'name', 'zip']);

        return response()->json([
            'success' => true,
            'data' => $city ? [
                'id' => $city->id,
                'name' => $city->name,
                'zip' => $city->zip,
                'label' => trim($city->name . ($city->zip ? ', ' . $city->zip : '')),
            ] : null,
        ]);
    }
}
