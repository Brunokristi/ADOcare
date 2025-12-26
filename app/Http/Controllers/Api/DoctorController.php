<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class DoctorController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $branchId = (int) $request->query('branch_id');

        if ($branchId <= 0) {
            return $this->error('branch_id is required', 422);
        }

        $query = Doctor::query()
            ->withExists([
                'branches as is_favourite' => fn ($q) => $q->where('branches.id', $branchId),
            ]);

        if ($request->boolean('favourites')) {
            $query->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId));
        }

        if (!$request->filled('sort')) {
            $query->orderBy('last_name')->orderBy('first_name');
        }

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['first_name', 'last_name', 'title', 'zpr', 'pzs']
        );

        return $this->success(new BaseCollection($results), 'Doctors retrieved');
    }

    public function favourites(Request $request)
    {
        $branchId = $request->user()->branch_id; // adjust if needed

        $query = Doctor::query()
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))
            ->withExists([
                'branches as is_favourite' => fn ($q) => $q->where('branches.id', $branchId),
            ]);

        if (!$request->filled('sort')) {
            $query->orderBy('last_name')->orderBy('first_name');
        }

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['first_name', 'last_name', 'title', 'zpr', 'pzs']
        );

        return $this->success(new BaseCollection($results), 'Favourite doctors retrieved');
    }

    public function store(\App\Http\Requests\StoreDoctorRequest $request)
    {
        $item = Doctor::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(Doctor $doctor)
    {
        return $this->success($doctor, 'Doctor retrieved');
    }

    public function update(\App\Http\Requests\UpdateDoctorRequest $request, Doctor $doctor)
    {
        $doctor->update($request->all());
        return $this->success($doctor, 'Updated');
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
