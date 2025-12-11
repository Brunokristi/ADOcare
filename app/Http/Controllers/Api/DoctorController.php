<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Doctor::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = Doctor::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(Doctor $doctor)
    {
        return $doctor;
    }

    public function update(Request $request, Doctor $doctor)
    {
        $doctor->update($request->all());
        return response()->json($doctor);
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
