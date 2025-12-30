<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiagnosisController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Diagnosis::query();

        // Default alphabetical ordering (unless client provides ?sort=...)
        if (!$request->filled('sort')) {
            $query->orderBy('code');
        }

        // Server-side: search + sort + pagination handled by ApiQuery
        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['code', 'description']
        );

        // Return the same paginated collection shape as doctors
        return $this->success(new BaseCollection($results), 'Diagnoses retrieved');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:diagnoses,code'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $diagnosis = Diagnosis::create($validated);

        return $this->success($diagnosis, 'Created', Response::HTTP_CREATED);
    }

    public function show(Diagnosis $diagnosis)
    {
        return $this->success($diagnosis, 'Diagnosis retrieved');
    }

    public function update(Request $request, Diagnosis $diagnosis)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:diagnoses,code,' . $diagnosis->id],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $diagnosis->update($validated);

        return $this->success($diagnosis, 'Updated');
    }

    public function destroy(Diagnosis $diagnosis)
    {
        $diagnosis->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
