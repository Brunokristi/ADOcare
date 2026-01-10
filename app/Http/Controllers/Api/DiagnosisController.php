<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\DiagnosisResource;
use App\Http\Requests\DiagnosisRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiagnosisController extends Controller
{
    public function index(Request $request)
    {
        $query = Diagnosis::query();

        // Server-side: search + sort + pagination handled by ApiQuery
        $results = ApiQuery::apply(
            $request,
            $query,
             ['code', 'description'],
            [],
            ['sort' => 'code']

        );

        // Return the same paginated collection shape as doctors
        return $this->success(new BaseCollection($results), 'Diagnoses retrieved');
    }

    public function store(DiagnosisRequest $request)
    {
        $validated = $request->validated();

        $diagnosis = Diagnosis::create($validated);

        return $this->success(new DiagnosisResource($diagnosis), 'Created', Response::HTTP_CREATED);
    }

    public function show(Diagnosis $diagnosis)
    {
        return $this->success($diagnosis, 'Diagnosis retrieved');
    }

    public function update(DiagnosisRequest $request, Diagnosis $diagnosis)
    {
        $validated = $request->validated();

        $diagnosis->update($validated);

        return $this->success($diagnosis, 'Updated');
    }

    public function destroy(Diagnosis $diagnosis)
    {
        $diagnosis->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
