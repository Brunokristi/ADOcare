<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate request payload for sending document or invoice emails.
 */
class SendDocumentsEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'ids' => ['required_without:invoice_ids', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:documents,id'],
            'invoice_ids' => ['required_without:ids', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'distinct', 'exists:invoices,id'],
        ];
    }
}
