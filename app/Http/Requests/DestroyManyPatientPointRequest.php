<?php

namespace App\Http\Requests;

use App\Models\PatientPoint;

class DestroyManyPatientPointRequest extends DestroyManyRequest
{
    protected function modelClass(): string
    {
        return PatientPoint::class;
    }
}
