<?php

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Support\Facades\DB;

class DoctorService
{
    /**
     * Build the base query used by the doctors index.
     *
     * @param int|null $branchId If provided, mark favourites for this branch.
     */
    public static function indexQuery(?int $branchId = null)
    {
        $query = Doctor::query()->select('doctors.*');

        if (!$branchId) {
            return $query;
        }

        $query->leftJoin('branch_favourite_doctors as bfd', function ($join) use ($branchId) {
            $join->on('bfd.doctor_id', '=', 'doctors.id')
                ->where('bfd.branch_id', '=', $branchId);
        });

        $query->addSelect(DB::raw('CASE WHEN bfd.doctor_id IS NULL THEN 0 ELSE 1 END AS is_favourite'));

        return $query;
    }
}
