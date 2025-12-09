<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiagnosisController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q', '');

        $query = Diagnosis::query();

        if ($q !== '') {
            $qLower = mb_strtolower($q);

            $query->where(function ($sub) use ($qLower) {
                $sub->whereRaw('LOWER(code) LIKE ?', ["%{$qLower}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$qLower}%"]);
            });
        }

        return $query
            ->orderBy('code')
            ->limit(50)
            ->get(['id', 'code', 'description']);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:diagnoses,code'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $diagnosis = Diagnosis::create($validated);

        return response()->json($diagnosis, Response::HTTP_CREATED);
    }

    public function show(Diagnosis $diagnosis)
    {
        return $diagnosis;
    }

    public function update(Request $request, Diagnosis $diagnosis)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:diagnoses,code,' . $diagnosis->id],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $diagnosis->update($validated);

        return response()->json($diagnosis);
    }

    public function destroy(Diagnosis $diagnosis)
    {
        $diagnosis->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
