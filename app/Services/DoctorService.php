<?php

namespace App\Services;

use App\Models\Doctor;

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
            // no favourites marking if branch not known
            return $query;
        }

        // Add is_favourite as computed boolean via left join
        $query->leftJoin('branch_favourite_doctors as bfd', function ($join) use ($branchId) {
            $join->on('bfd.doctor_id', '=', 'doctors.id')
                ->where('bfd.branch_id', '=', $branchId);
        });

        // Select computed field
        $query->addSelect(\DB::raw('(bfd.doctor_id IS NOT NULL) AS is_favourite'));

        return $query;
    }
}
