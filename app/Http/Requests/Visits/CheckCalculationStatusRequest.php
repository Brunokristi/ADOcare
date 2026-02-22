<?php
namespace App\Http\Requests\Visits;

use Illuminate\Foundation\Http\FormRequest;

class CheckCalculationStatusRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'month' => 'required|date',
            'branch_id' => 'required|integer|exists:branches,id',
            'user_id' => 'nullable|integer',
        ];
    }
}
