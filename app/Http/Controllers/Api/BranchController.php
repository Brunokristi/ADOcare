<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\DoctorCollection;
use App\Http\Resources\DoctorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $query = Branch::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Branches retrieved');
    }

    public function patients(Branch $branch)
    {
        $query = Patient::with(['doctor', 'visits', 'insuranceCompany'])
            ->whereHas('assignedUsers', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            });

        $results = ApiQuery::apply(request(), $query);
        return $this->success(new PatientCollection($results), 'Branch patients retrieved');
    }

    public function doctors(Branch $branch)
    {
        $doctors = Doctor::query()->get();
        return DoctorResource::collection($doctors);
    }

    public function store(\App\Http\Requests\StoreBranchRequest $request)
    {
        $item = Branch::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(Branch $branch)
    {
        return $this->success($branch, 'Branch retrieved');
    }

    public function update(\App\Http\Requests\UpdateBranchRequest $request, Branch $branch)
    {
        $branch->update($request->all());
        return $this->success($branch, 'Updated');
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
