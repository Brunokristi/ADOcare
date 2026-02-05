<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Jobs\CalculateVisitsTimeline;
use Carbon\Carbon;

class VisitsController extends \Illuminate\Routing\Controller
{
    public function monthTimeline(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date',
            'branch_id' => 'required|integer|exists:branches,id',
            'user_id' => 'nullable|integer',
            'procedure_codes' => 'nullable|array',
            'procedure_codes.*' => 'string',
            'patients' => 'nullable|array',
            'patients.*' => 'integer',
            'persist' => 'nullable|boolean',
        ]);

        if (!isset($data['user_id']) || !$data['user_id']) {
            $data['user_id'] = Auth::id();
        }

        // Record the calculation request in DB
        $month = Carbon::parse($data['month'])->toDateString();
        DB::table('visit_calculations')->updateOrInsert(
            [
                'user_id' => $data['user_id'],
                'branch_id' => $data['branch_id'],
                'month' => $month,
            ],
            [
                'status' => 'pending',
                'error_message' => null,
            ]
        );

        CalculateVisitsTimeline::dispatch($data);

        return response()->json([
            'success' => true,
            'message' => 'Timeline calculation has been queued and will be processed in the background.',
            'data' => [
                'queued' => true,
            ],
        ]);
    }

    public function checkCalculationStatus(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date',
            'branch_id' => 'required|integer|exists:branches,id',
            'user_id' => 'nullable|integer',
        ]);

        if (!isset($data['user_id']) || !$data['user_id']) {
            $data['user_id'] = Auth::id();
        }

        $month = Carbon::parse($data['month'])->toDateString();

        $calculation = DB::table('visit_calculations')
            ->where('user_id', $data['user_id'])
            ->where('branch_id', $data['branch_id'])
            ->where('month', $month)
            ->first();

        if (!$calculation) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'not_found',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $calculation->status,
                'completed_at' => $calculation->completed_at,
                'error_message' => $calculation->error_message,
            ],
        ]);
    }

    public function dayTotals(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'user_id' => 'nullable|integer',
            'branch_id' => 'required|integer|exists:branches,id',
            'include_on_location' => 'nullable|boolean',
        ]);

        $userId = (int)($data['user_id'] ?? Auth::id());
        $branchId = (int)$data['branch_id'];
        $date = $data['date'];
        $includeOnLocation = (bool)($data['include_on_location'] ?? true);

        $agg = DB::table('visits')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('date', $date)
            ->selectRaw('
                COUNT(*) as stops,
                COALESCE(SUM(time_to_location), 0) as travel_seconds,
                COALESCE(SUM(distance_to_location), 0) as distance_m,
                COALESCE(SUM(time_on_location), 0) as on_location_seconds,
                MIN(COALESCE(terrain_time, administrative_time)) as first_arrival,
                MAX(COALESCE(terrain_time, administrative_time)) as last_arrival
            ')
            ->first();

        $totalSeconds = (int)$agg->travel_seconds + ($includeOnLocation ? (int)$agg->on_location_seconds : 0);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'stops' => (int)$agg->stops,
                'travel_seconds' => (int)$agg->travel_seconds,
                'on_location_seconds' => (int)$agg->on_location_seconds,
                'total_seconds' => $totalSeconds,
                'distance_m' => (int)$agg->distance_m,
                'distance_km' => round(((int)$agg->distance_m) / 1000, 2),
                'first_arrival' => $agg->first_arrival,
                'last_arrival' => $agg->last_arrival,
            ],
        ]);
    }

    public function monthTotals(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date', // any date within month
            'user_id' => 'nullable|integer',
            'branch_id' => 'required|integer|exists:branches,id',
            'include_on_location' => 'nullable|boolean',
        ]);

        $tz = 'Europe/Bratislava';
        $userId = (int)($data['user_id'] ?? Auth::id());
        $branchId = (int)$data['branch_id'];
        $includeOnLocation = (bool)($data['include_on_location'] ?? true);

        $month = Carbon::parse($data['month'])->setTimezone($tz);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        $agg = DB::table('visits')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COUNT(*) as stops,
                COALESCE(SUM(time_to_location), 0) as travel_seconds,
                COALESCE(SUM(distance_to_location), 0) as distance_m,
                COALESCE(SUM(time_on_location), 0) as on_location_seconds,
                MIN(date) as from_date,
                MAX(date) as to_date
            ')
            ->first();

        $totalSeconds = (int)$agg->travel_seconds + ($includeOnLocation ? (int)$agg->on_location_seconds : 0);

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month->format('Y-m'),
                'from' => $from,
                'to' => $to,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'stops' => (int)$agg->stops,
                'travel_seconds' => (int)$agg->travel_seconds,
                'on_location_seconds' => (int)$agg->on_location_seconds,
                'total_seconds' => $totalSeconds,
                'distance_m' => (int)$agg->distance_m,
                'distance_km' => round(((int)$agg->distance_m) / 1000, 2),
            ],
        ]);
    }
}