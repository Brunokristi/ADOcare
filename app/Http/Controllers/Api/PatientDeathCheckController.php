<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function show(string $patientId)
    {
        $needle = preg_replace('/\s+/', '', trim($patientId));

        \Log::debug('[UDZS] Death check requested', ['input' => $patientId, 'needle' => $needle]);

        $patient = \App\Models\Patient::query()
            ->where('id', $patientId)
            ->when($needle !== '', function ($query) use ($needle) {
                $query->orWhereRaw("replace(personal_number, ' ', '') = ?", [$needle]);
            })
            ->first();

        if (!$patient) {
            \Log::warning('[UDZS] Patient not found', ['input' => $patientId]);
            return $this->notFound('Patient not found');
        }

        \Log::debug('[UDZS] Patient found', ['id' => $patient->id, 'personal_number' => $patient->personal_number]);

        $result = $this->service->checkPersonalNumber((string) ($patient->personal_number ?? ''));

        \Log::debug('[UDZS] Death check result', ['result' => $result]);

        return $this->success($result, 'OK');
    }
}
