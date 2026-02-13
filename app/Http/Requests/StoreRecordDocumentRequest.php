<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordDocumentRequest extends FormRequest
{
    public function authorize()
    {
        // authorize in controller/policy where necessary; allow here for authenticated users
        return $this->user() != null;
    }

    public function rules()
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'record_data' => 'required|array',
        ];
    }
}
