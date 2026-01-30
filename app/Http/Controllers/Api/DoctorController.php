<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Services\DoctorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DoctorController extends Controller
{
    /**
     * List doctors
     *
     * @group Doctors
     * @queryParam q string Search query. Example: "Smith"
     * @response 200 {"data":[{"id":1,"first_name":"John","last_name":"Smith"}],"meta":{"total":1}}
     */
    public function index()
    {

        $branchId = request()->input('mark_favourites_for_branch_id');
        $query = DoctorService::indexQuery($branchId);

        $results = ApiQuery::apply(
            request(),
            $query,
            searchable: ['first_name', 'last_name', 'zpr', 'pzs'],
            allowedFilters: ['first_name', 'last_name', 'title', 'zpr', 'pzs', 'is_favourite'],
        );

        return $this->success(new BaseCollection($results), 'Doctors retrieved');
    }

    /**
     * Create a doctor
     *
     * @group Doctors
     * @bodyParam first_name string required Example: "John"
     * @bodyParam last_name string required Example: "Smith"
     * @response 201 {"id":1,"first_name":"John","last_name":"Smith"}
     */
    public function store(\App\Http\Requests\StoreDoctorRequest $request)
    {
        $item = Doctor::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    /**
     * Get a doctor
     *
     * @group Doctors
     * @urlParam doctor int required Doctor ID. Example: 1
     * @response 200 {"id":1,"first_name":"John","last_name":"Smith"}
     */
    public function show(Doctor $doctor)
    {
        return $this->success($doctor, 'Doctor retrieved');
    }

    /**
     * Update a doctor
     *
     * @group Doctors
     * @urlParam doctor int required Doctor ID. Example: 1
     * @bodyParam first_name string Example: "John"
     * @bodyParam last_name string Example: "Smith"
     * @response 200 {"id":1,"first_name":"John","last_name":"Smith"}
     */
    public function update(\App\Http\Requests\UpdateDoctorRequest $request, Doctor $doctor)
    {
        $doctor->update($request->all());
        return $this->success($doctor, 'Updated');
    }

    /**
     * Delete a doctor
     *
     * @group Doctors
     * @urlParam doctor int required Doctor ID. Example: 1
     * @response 204 {}
     */
    public function destroy(Doctor $doctor)
    {
        $doctor->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
