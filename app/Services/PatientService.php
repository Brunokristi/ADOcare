<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PatientService
{
    public function queryForUserBranch($user, Branch $branch): Builder
    {
        return Patient::with(['doctor', 'visits', 'insuranceCompany'])
            ->whereHas('assignedUsers', function ($q) use ($user, $branch) {
                $q->where('users.id', $user->id)
                    ->where('patient_branch_users.branch_id', $branch->id);
            });
    }

    public function create(array $data, $user, int $branchId): Patient
    {
        return DB::transaction(function () use ($data, $user, $branchId) {
            $patient = Patient::create(collect($data)->except('branch_id')->toArray());

            $patient->assignedUsers()->syncWithoutDetaching([
                $user->id => ['branch_id' => $branchId],
            ]);

            $patient->load(['doctor', 'visits', 'insuranceCompany']);

            return $patient;
        });
    }

    public function update(Patient $patient, array $data, $user, ?int $branchId = null): Patient
    {
        return DB::transaction(function () use ($patient, $data, $user, $branchId) {

            if (array_key_exists('dekurz_number', $data)) {
                $incoming = (int) $data['dekurz_number'];
                $current  = (int) ($patient->dekurz_number ?? 0);

                if ($incoming <= $current) {
                    unset($data['dekurz_number']);
                }
            }

            $patient->fill(collect($data)->except('branch_id')->toArray());
            $patient->save();

            if (!is_null($branchId)) {
                $patient->assignedUsers()->syncWithoutDetaching([
                    $user->id => ['branch_id' => $branchId],
                ]);
            }

            $patient->load(['doctor', 'visits', 'insuranceCompany']);

            return $patient;
        });
    }


    public function delete(Patient $patient): void
    {
        $patient->delete();
    }

    public function deleteManyByIds(array $ids): void
    {
        Patient::whereIn('id', $ids)->delete();
    }

    public function deleteManyInBranch(array $ids, Branch $branch): void
    {
        Patient::whereHas('assignedUsers', function ($q) use ($branch) {
            $q->where('patient_branch_users.branch_id', $branch->id);
        })->whereIn('id', $ids)->delete();
    }

    public function ensureAssignedToBranch(Patient $patient, Branch $branch): bool
    {
        return $patient->assignedUsers()->where('patient_branch_users.branch_id', $branch->id)->exists();
    }

    public function findWithRelations(int $id): ?Patient
    {
        return Patient::with(['doctor', 'visits', 'insuranceCompany'])->find($id);
    }
}
