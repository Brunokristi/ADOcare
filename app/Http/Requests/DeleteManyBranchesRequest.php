<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Branch;

class DeleteManyBranchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            $ids = $this->input('ids', []);
            if (empty($ids)) return false;

            $branches = Branch::whereIn('id', $ids)->get();
            foreach ($branches as $branch) {
                if ($branch->company_id != $user->company_id) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:branches,id',
        ];
    }
}