<?php

namespace App\Http\Requests;

use App\Models\Company;

class DestroyManyCompanyRequest extends DestroyManyRequest
{
    protected function modelClass(): string
    {
        return Company::class;
    }
}
