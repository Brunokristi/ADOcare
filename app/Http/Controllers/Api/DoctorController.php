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
        $query = Doctor::query();
        $results = ApiQuery::apply(request(), $query);
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
