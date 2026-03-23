<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'insurance_company_id' => ['nullable', 'integer', 'exists:insurance_companies,id'],
            'period' => ['required', 'date_format:Y-m'],
            'type' => ['required', 'string', 'in:procedures,transport,credit_note,debit_note'],
            'amount' => ['required_if:type,credit_note,debit_note', 'numeric'],
            'related_invoice_id' => ['required_if:type,credit_note,debit_note', 'integer', 'exists:invoices,id'],
        ];
    }
}
