<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\UdzsDeathService;

class PatientDeathCheckController extends Controller
{
    public function __construct(protected UdzsDeathService $service)
    {
    }

    /**
     * Check if a patient is deceased using UDZS API.
     *
     * @group Patients
     * @response 200 {"message":"OK","data":{"status":"alive","data":null}}
     */
    public function show(Patient $patient)
    {

        $needle = preg_replace('/\s+/', '', trim($patient->id));

        \Log::debug('[UDZS] Death check requested', ['input' => $patient->id, 'needle' => $needle]);

        if (!$patient) {
            \Log::warning('[UDZS] Patient not found', ['input' => $patient->id]);
            return $this->notFound('Patient not found');
        }

        \Log::debug('[UDZS] Patient found', ['id' => $patient->id, 'personal_number' => $patient->personal_number]);

        $result = $this->service->checkPersonalNumber((string) ($patient->personal_number ?? ''));

        \Log::debug('[UDZS] Death check result', ['result' => $result]);

        return $this->success($result, 'OK');
    }
}
