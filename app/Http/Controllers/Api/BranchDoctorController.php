<?php

namespace App\Http\Controllers\Api;

use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\Doctor;
use Illuminate\Routing\Controller;

class BranchDoctorController extends Controller
{
    use ApiResponse;

    public function attach(Branch $branch, Doctor $doctor)
    {
        // inserts into branch_doctor if not already there
        $branch->doctors()->syncWithoutDetaching([$doctor->id]);

        return $this->success(null, 'Doctor added to favourites');
    }

    public function detach(Branch $branch, Doctor $doctor)
    {
        // deletes from branch_doctor
        $branch->doctors()->detach($doctor->id);

        return $this->success(null, 'Doctor removed from favourites');
    }
}
