<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PatientService
{
    public function queryForUserBranch(User $nurse, Branch $branch): Builder
    {
        return Patient::with(['doctor', 'visits', 'insuranceCompany'])
            ->where('nurse_id', $nurse->id)
            ->where('branch_id', $branch->id);
    }

    // FUnction to check if branch_id or nurse_id are being set by non-manager user
    public function checkManagerProtectedFields(array $data): void
    {
        // branch_id and nurse_id are manager protected. If user is not manager, throw error
        $user = auth()->user();
        if (($data['branch_id'] || $data['nurse_id']) && !$user->hasRole('manager')) {
            throw new \Exception("Unauthorized to set branch_id or nurse_id");
        }
    }

    public function create(array $data): Patient
    {
        $this->checkManagerProtectedFields($data);

        $user = auth()->user();
        $data['nurse_id'] = $user->id;

        return DB::transaction(function () use ($data) {
            $patient = Patient::create($data);
            $patient->load(['doctor', 'visits', 'insuranceCompany']);

            return $patient;
        });
    }

    public function update(Patient $patient, array $data): Patient
    {

        $this->checkManagerProtectedFields($data);

        return DB::transaction(function () use ($patient, $data) {

            if (array_key_exists('dekurz_number', $data)) {
                $incoming = (int) $data['dekurz_number'];
                $current = (int) ($patient->dekurz_number ?? 0);

                if ($incoming <= $current) {
                    unset($data['dekurz_number']);
                }
            }

            $patient->update($data);
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
        Patient::where('branch_id', $branch->id)->whereIn('id', $ids)->delete();
    }

    public function ensureAssignedToBranch(Patient $patient, Branch $branch): bool
    {
        return $patient->branch_id === $branch->id;
    }

    public function findWithRelations(int $id): ?Patient
    {
        return Patient::with(['doctor', 'visits', 'insuranceCompany'])->find($id);
    }
}
