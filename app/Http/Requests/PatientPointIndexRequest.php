<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientPointIndexRequest extends ApiQueryRequest
{
    public function rules()
    {
        return array_merge(parent::rules(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
    }
}
