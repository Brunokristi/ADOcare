<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function index()
    {
        $patients = DB::table('patients')->get();

        return response()->json($patients);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'personal_number' => 'nullable|string',
            'sex' => 'nullable|in:M,F',
        ]);

        $id = DB::table('patients')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id], 201);
    }

    public function show($id)
    {
        $patient = DB::table('patients')->where('id', $id)->first();
        if (! $patient) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($patient);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'personal_number' => 'nullable|string',
            'sex' => 'nullable|in:M,F',
        ]);

        $updated = DB::table('patients')->where('id', $id)->update($data + ['updated_at' => now()]);
        if (! $updated) {
            return response()->json(['message' => 'Not found or no changes'], 404);
        }

        return response()->json(['message' => 'Updated']);
    }

    public function destroy($id)
    {
        $deleted = DB::table('patients')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['message' => 'Deleted']);
    }
}
