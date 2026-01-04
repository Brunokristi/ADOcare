<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DekurzController extends Controller
{
    /**
     * Get unique dates for specific procedure codes
     * GET /v1/dekurz/dates
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uniqueDates(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $dates = PatientPoint::query()
            ->where('patient_id', $validated['patient_id'])
            ->whereDate('date', '>=', $validated['start_date'])
            ->whereDate('date', '<=', $validated['end_date'])
            ->whereIn('procedure_code', ['3439', '3440'])
            ->distinct()
            ->pluck('date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->sort()
            ->values();

        return response()->json([
            'patient_id' => $validated['patient_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'dates' => $dates,
            'count' => $dates->count(),
        ]);
    }
}
