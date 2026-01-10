<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Requests\BranchPatientStoreRequest;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\BranchPatientUpdateRequest;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Services\PatientService;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Http\Request;

class BranchPatientController extends Controller
{


    private PatientService $service;

    public function __construct(PatientService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, Branch $branch)
    {
        $user = $request->user();

        $query = $this->service->queryForUserBranch($user, $branch);

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['first_name', 'last_name', 'personal_number'],
            allowedFilters: ['sex'],
            defaults: ['sort' => 'last_name']
        );

        return $this->success(new PatientCollection($results), 'Patients retrieved');
    }

    public function store(BranchPatientStoreRequest $request, Branch $branch)
    {
        $data = $request->validated();

        $patient = $this->service->create($data, $request->user(), $branch->id);

        return $this->success(new PatientResource($patient), 'Created', 201);
    }

    public function destroyMany(BulkDeleteRequest $request, Branch $branch)
    {
        $ids = $request->validated()['ids'];

        $this->service->deleteManyInBranch($ids, $branch);

        return $this->success(null, 'Deleted');
    }

    /**
     * GET /v1/branches/{branch}/patients/{patient}
     */
    public function show(Request $request, Branch $branch, Patient $patient)
    {
        if (!$this->service->ensureAssignedToBranch($patient, $branch)) {
            return $this->error('Not found in branch', 404);
        }

        return $this->success(new PatientResource($patient->load(['doctor', 'visits', 'insuranceCompany'])), 'Patient retrieved');
    }

    /**
     * PUT/PATCH /v1/branches/{branch}/patients/{patient}
     */
    public function update(BranchPatientUpdateRequest $request, Branch $branch, Patient $patient)
    {
        if (!$this->service->ensureAssignedToBranch($patient, $branch)) {
            return $this->error('Not found in branch', 404);
        }

        $data = $request->validated();
        $patient = $this->service->update($patient, $data, $request->user());

        return $this->success(new PatientResource($patient), 'Updated');
    }

    /**
     * DELETE /v1/branches/{branch}/patients/{patient}
     */
    public function destroy(Request $request, Branch $branch, Patient $patient)
    {
        if (!$this->service->ensureAssignedToBranch($patient, $branch)) {
            return $this->error('Not found in branch', 404);
        }

        $this->service->delete($patient);

        return $this->success(null, 'Deleted');
    }
}
