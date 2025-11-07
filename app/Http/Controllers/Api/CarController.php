<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CarController extends Controller
{
    public function index()
    {
        $cars = DB::table('cars')->get();

        return response()->json($cars);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'evc' => 'required|string|max:255',
            'company_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ]);

        $id = DB::table('cars')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id], 201);
    }

    public function show($id)
    {
        $car = DB::table('cars')->where('id', $id)->first();
        if (! $car) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($car);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'evc' => 'sometimes|required|string|max:255',
            'company_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ]);

        $updated = DB::table('cars')->where('id', $id)->update($data + ['updated_at' => now()]);
        if (! $updated) {
            return response()->json(['message' => 'Not found or no changes'], 404);
        }

        return response()->json(['message' => 'Updated']);
    }

    public function destroy($id)
    {
        $deleted = DB::table('cars')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['message' => 'Deleted']);
    }
}
