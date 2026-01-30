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
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(?int $branchId = null)
    {

        if (!$branchId) {
            return Doctor::query();
        }


        $subquery = Doctor::query();
        // Add is_favourite attribute via LEFT JOIN for better performance
        $subquery->leftJoin('branch_favourite_doctors as bfd', function ($join) use ($branchId) {
            $join->on('bfd.doctor_id', '=', 'doctors.id')
                ->where('bfd.branch_id', '=', $branchId);
        })
            ->selectRaw('doctors.*, (bfd.doctor_id IS NOT NULL) AS is_favourite');

        $query = DB::table(DB::raw("({$subquery->toSql()}) as doctors"))
            ->mergeBindings($subquery->getQuery()) // Merge bindings from subquery
            ->select('doctors.*');

        return $query;
    }
}
