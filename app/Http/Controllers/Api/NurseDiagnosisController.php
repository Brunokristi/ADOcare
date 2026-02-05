<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Requests\NurseDiagnosisRequest;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\DiagnosisResource;
use App\Http\Requests\DiagnosisRequest;
use App\Http\Resources\NurseDiagnosisResource;
use App\Http\Responses\ApiResponse;
use App\Models\NurseDiagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NurseDiagnosisController extends Controller
{
    public function index(Request $request)
    {
        $query = NurseDiagnosis::query();

        $results = ApiQuery::apply(
            $request,
            $query,
            ['code', 'description'],
            [],
            ['sort' => 'code']

        );

        return $this->success(new BaseCollection($results), 'Diagnoses retrieved');
    }

    public function store(NurseDiagnosisRequest $request)
    {
        $validated = $request->validated();

        $diagnosis = NurseDiagnosis::create($validated);
        return $this->success(new NurseDiagnosisResource($diagnosis), 'Created', Response::HTTP_CREATED);
    }

    public function show(NurseDiagnosis $diagnosis)
    {
        return $this->success($diagnosis, 'Diagnosis retrieved');
    }

    public function update(NurseDiagnosisRequest $request, NurseDiagnosis $diagnosis)
    {
        $validated = $request->validated();

        $diagnosis->update($validated);

        return $this->success($diagnosis, 'Updated');
    }

    public function destroy(NurseDiagnosis $diagnosis)
    {
        $diagnosis->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
