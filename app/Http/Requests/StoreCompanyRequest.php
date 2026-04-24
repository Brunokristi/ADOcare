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
            'name' => 'required|string|max:255',
            'register' => 'nullable|string|max:255',
            'ico' => 'nullable|string|max:32',
            'dic' => 'nullable|string|max:32',
            'ic_dph' => 'nullable|string|max:32',
            'iban' => 'nullable|string|max:64',
            'bic' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'psc' => 'nullable|string|max:32',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'invoice_number' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'representative_id' => 'nullable|integer|exists:users,id',
            'send_notifications' => 'nullable|boolean',
            'notification_settings' => 'nullable|json',
            'visit_locations' => 'nullable|json',
        ];
    }
}
