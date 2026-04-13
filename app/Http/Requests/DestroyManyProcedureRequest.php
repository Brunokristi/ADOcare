<?php

namespace App\Http\Requests;

use App\Models\Procedure;

class DestroyManyProcedureRequest extends DestroyManyRequest
{
    protected function modelClass(): string
    {
        return Procedure::class;
    }
}
