<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiQueryRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'q' => 'nullable|string',
            'sort' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'all' => 'nullable|boolean',
            'with' => 'nullable|string',
            'count' => 'nullable|string',
            // generic filter param can be an array or string depending on client
            'filter' => 'nullable',
        ];
    }
}
