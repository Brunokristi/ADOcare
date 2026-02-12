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
use App\Models\User;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Branches retrieved');
    }



    public function patients(Branch $branch)
    {
        $query = Patient::with(['doctor', 'visits', 'insuranceCompany'])
            ->where('branch_id', $branch->id);

        $results = ApiQuery::apply(request(), $query);
        return $this->success(new PatientCollection($results), 'Branch patients retrieved');
    }

    public function users(Branch $branch)
    {
        $usersQuery = User::query()->whereHas('branches', function ($q) use ($branch) {
            $q->where('branch_id', $branch->id);
        });

        $results = ApiQuery::apply(request(), $usersQuery);

        return $this->success(new BaseCollection($results), 'Branch users retrieved');
    }

    public function nurses(Branch $branch)
    {
        $nursesQuery = User::query()
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->whereHas('roles', function ($q) {
                $q->where('position', 'nurse');
            });

        $results = ApiQuery::apply(request(), $nursesQuery);

        return $this->success(new BaseCollection($results), 'Branch nurses retrieved');
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
