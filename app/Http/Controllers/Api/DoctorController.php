<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DoctorController extends Controller
{
    public function index()
    {

        $query = Doctor::query();

        $branchId = request()->input('mark_favourites_for_branch_id');
        if ($branchId) {
            // Add is_favourite attribute via LEFT JOIN for better performance
            $query->leftJoin('branch_favourite_doctors as bfd', function ($join) use ($branchId) {
                $join->on('bfd.doctor_id', '=', 'doctors.id')
                     ->where('bfd.branch_id', '=', $branchId);
            })
            ->selectRaw('doctors.*, (bfd.doctor_id IS NOT NULL) AS is_favourite');
        }

        $results = ApiQuery::apply(
            request(),
            $query,
            searchable: ['first_name', 'last_name', 'zpr', 'pzs'],
        );

        return $this->success(new BaseCollection($results), 'Doctors retrieved');
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
