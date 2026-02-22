<?php
namespace App\Http\Requests\Visits;

use Illuminate\Foundation\Http\FormRequest;

class DayTotalsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'date' => 'required|date_format:Y-m-d',
            'user_id' => 'nullable|integer',
            'branch_id' => 'required|integer|exists:branches,id',
            'include_on_location' => 'nullable|boolean',
        ];
    }
}
