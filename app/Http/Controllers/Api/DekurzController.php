<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPointUniqueDatesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use App\Models\PatientPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DekurzController extends Controller
{
    /**
     * Get unique dates for specific procedure codes
     * GET /v1/dekurz/dates
     *
     * @param PatientPointUniqueDatesRequest $patient
     * @return \Illuminate\Http\JsonResponse
     */
    public function uniqueDates(PatientPointUniqueDatesRequest $request, Patient $patient)
    {
        $dates = PatientPoint::query()
            ->where('patient_id', $patient->id)
            ->whereDate('date', '>=', $request->input('start_date'))
            ->whereDate('date', '<=', $request->input('end_date'))
            ->whereIn('procedure_code', ['3439', '3440'])
            ->distinct()
            ->pluck('date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->sort()
            ->values();

        $res_data= [
            'patient_id' => $patient->id,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'dates' => $dates,
            'count' => $dates->count(),
        ];

        return $this->success($res_data, 'Unique dates retrieved');
    }
}
