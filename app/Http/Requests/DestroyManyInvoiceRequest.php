<?php

namespace App\Http\Requests;

use App\Models\Invoice;

class DestroyManyInvoiceRequest extends DestroyManyRequest
{
    protected function modelClass(): string
    {
        return Invoice::class;
    }
}
