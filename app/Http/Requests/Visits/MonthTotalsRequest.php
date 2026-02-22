<?php
namespace App\Http\Requests\Visits;

use Illuminate\Foundation\Http\FormRequest;

class MonthTotalsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'month' => 'required|date',
            'user_id' => 'nullable|integer',
            'branch_id' => 'required|integer|exists:branches,id',
            'include_on_location' => 'nullable|boolean',
        ];
    }
}
