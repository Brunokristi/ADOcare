<?php

namespace App\Http\Requests;

use App\Models\Macro;

class DestroyManyMacroRequest extends DestroyManyRequest
{
    protected function modelClass(): string
    {
        return Macro::class;
    }
}
