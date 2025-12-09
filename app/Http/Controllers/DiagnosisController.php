<?php

namespace App\Http\Controllers;

use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiagnosisController extends Controller
{
    /**
     * GET /v1/diagnoses
     * Optional search: ?q=...
     */
    public function index(Request $request)
    {
        $q = $request->query('q', '');

        $query = Diagnosis::query();

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // for autocomplete you might prefer ->limit(20)->get()
        // for generic listing, pagination is nicer:
        if ($request->boolean('paginate', false)) {
            return $query
                ->orderBy('code')
                ->paginate(20, ['id', 'code', 'description']);
        }

        return $query
            ->orderBy('code')
            ->limit(50)
            ->get(['id', 'code', 'description']);
    }

    /**
     * POST /v1/diagnoses
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:diagnoses,code'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $diagnosis = Diagnosis::create($validated);

        return response()->json($diagnosis, Response::HTTP_CREATED);
    }

    /**
     * GET /v1/diagnoses/{diagnosis}
     */
    public function show(Diagnosis $diagnosis)
    {
        return $diagnosis;
    }

    /**
     * PUT/PATCH /v1/diagnoses/{diagnosis}
     */
    public function update(Request $request, Diagnosis $diagnosis)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:diagnoses,code,' . $diagnosis->id],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $diagnosis->update($validated);

        return response()->json($diagnosis);
    }

    /**
     * DELETE /v1/diagnoses/{diagnosis}
     */
    public function destroy(Diagnosis $diagnosis)
    {
        $diagnosis->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
