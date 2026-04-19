<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDekurzRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() != null;
    }

    public function rules()
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'dekurz_number' => 'required|string|max:50',
            'month' => 'nullable|date',
            'sections' => 'required|array|min:1',
            'sections.*.text' => 'required|string',
            'sections.*.dates' => 'required|array|min:1',
            'sections.*.dates.*' => 'required|date_format:Y-m-d',
            'branch_id' => 'required|integer|exists:branches,id',
            'ai_feedback' => 'nullable|array',
            'ai_feedback.source' => 'nullable|string|max:64',
            'ai_feedback.proposal_document_id' => 'nullable|integer|exists:documents,id',
            'ai_feedback.suggested_sections' => 'required_with:ai_feedback|array|min:1',
            'ai_feedback.suggested_sections.*.text' => 'required|string',
        ];
    }
}
