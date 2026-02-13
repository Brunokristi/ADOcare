<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Branch;

class StoreAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branchId = $this->input('branch_id');
        if (!$branchId)
            return false;

        $branch = Branch::find($branchId);
        if (!$branch)
            return false;

        // allow if user belongs to branch or is manager/admin (BranchPolicy::view covers company scope)
        return $this->user()->can('view', $branch);
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'patient_id' => 'required|exists:patients,id',
            'branch_id' => 'required|exists:branches,id',
        ];
    }
}
