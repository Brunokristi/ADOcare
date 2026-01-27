<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\DoctorCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\Doctor;

class BranchDoctorController extends Controller
{
    public function doctors(Branch $branch)
    {
        $query = Doctor::query()
            ->whereHas('favourite_in_branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            });
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new DoctorCollection($results), 'Branch doctors retrieved');
    }

    public function attach(Branch $branch, Doctor $doctor)
    {
        // inserts into branch_favourite_doctors if not already there
        $branch->favourite_doctors()->syncWithoutDetaching([$doctor->id]);

        return $this->success(null, 'Doctor added to favourites');
    }

    public function detach(Branch $branch, Doctor $doctor)
    {
        // deletes from branch_favourite_doctors
        $branch->favourite_doctors()->detach($doctor->id);

        return $this->success(null, 'Doctor removed from favourites');
    }
}
