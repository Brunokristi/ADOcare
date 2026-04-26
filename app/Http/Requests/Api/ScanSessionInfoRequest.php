<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ScanSessionInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public token-based endpoint; access is validated by session token in controller/service.
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string'],
        ];
    }
}
