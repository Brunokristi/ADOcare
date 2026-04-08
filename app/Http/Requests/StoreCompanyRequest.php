<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'send_notifications' => 'nullable|boolean',
            'notification_settings' => 'nullable|json',
            'visit_locations' => 'nullable|json',
        ];
    }
}
