<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VisitsController extends \Illuminate\Routing\Controller
{
    /**
     * POST /v1/visits/timeline
     *
     * Returns exact arrive/leave timestamps per patient visit for each day in a month,
     * starting + ending at the branch.
     *
     * Handles multiple patients at the same coords by applying a tiny deterministic jitter
     * to each stop so the solver can return an unambiguous order.
     */
    public function monthTimeline(Request $request)
    {
        set_time_limit(300);
        $data = $request->validate([
            'month' => 'required|date',                  // any date inside the month
            'branch_id' => 'required|integer|exists:branches,id',
            'user_id' => 'nullable|integer',             // default: current user
            'start_time' => 'nullable|date_format:H:i',  // default: 07:00
            'procedure_codes' => 'nullable|array',
            'procedure_codes.*' => 'string',
            'patients' => 'nullable|array',
            'patients.*' => 'integer',
        ]);

        $tz = 'Europe/Bratislava';

        $userId = (int) ($data['user_id'] ?? Auth::id());
        $branchId = (int) $data['branch_id'];
        $startTimeHHmm = $data['start_time'] ?? '07:00';
        $procedureCodes = $data['procedure_codes'] ?? ['3439', '3440'];
        $filterPatientIds = array_values(array_filter(array_map('intval', $data['patients'] ?? [])));

        $month = Carbon::parse($data['month'])->setTimezone($tz);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $branch = DB::table('branches')
            ->where('id', $branchId)
            ->select('id', 'latitude', 'longitude', 'per_location_time')
            ->first();

        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'Branch not found'], 404);
        }

        if ($branch->latitude === null || $branch->longitude === null) {
            return response()->json([
                'success' => false,
                'message' => 'Branch has missing coordinates (latitude/longitude).',
            ], 422);
        }

        $perLocationSeconds = ((int) ($branch->per_location_time ?? 0)) * 60;
        if ($perLocationSeconds <= 0) {
            $perLocationSeconds = 10 * 60; // fallback: 10 minutes
        }

        // Load ALL visits for month (one row = one patient visit / patient_point)
        $rows = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($procedureCodes), fn ($q) => $q->whereIn('pp.procedure_code', $procedureCodes))
            ->when(!empty($filterPatientIds), fn ($q) => $q->whereIn('pp.patient_id', $filterPatientIds))
            ->select([
                'pp.id as patient_point_id',
                'pp.date',
                'pp.patient_id',
                'p.first_name',
                'p.last_name',
                'p.city as patient_city',
                'p.address as patient_address',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
            ])
            ->orderBy('pp.date')
            ->orderBy('pp.id')
            ->get();

        Log::info('VisitsTimeline month query', [
            'user_id' => $userId,
            'branch_id' => $branchId,
            'from' => $from,
            'to' => $to,
            'rows' => $rows->count(),
            'per_location_seconds' => $perLocationSeconds,
            'start_time' => $startTimeHHmm,
        ]);

        // Group visits per day (YYYY-MM-DD)
        $visitsByDay = [];
        foreach ($rows as $r) {
            $day = Carbon::parse($r->date)->toDateString();
            $visitsByDay[$day] ??= [];
            $visitsByDay[$day][] = $r;
        }

        // Build full month output (include empty days)
        $days = [];
        for ($d = 1; $d <= $month->daysInMonth; $d++) {
            $date = Carbon::create($month->year, $month->month, $d, 0, 0, 0, $tz)->toDateString();

            $startUnix = Carbon::parse($date . ' ' . $startTimeHHmm . ':00', $tz)->timestamp;

            $dayVisits = $visitsByDay[$date] ?? [];
            $timeline = $this->solveDayTimeline($date, $dayVisits, $branch, $startUnix, $perLocationSeconds);

            $days[] = $timeline;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'meta' => [
                    'month' => $month->format('Y-m'),
                    'from' => $from,
                    'to' => $to,
                    'user_id' => $userId,
                    'branch_id' => $branchId,
                    'start_time' => $startTimeHHmm,
                    'per_location_seconds' => $perLocationSeconds,
                ],
                'days' => $days,
            ],
        ]);
    }

    /**
     * GET /v1/visits/patient-time?patient_id=..&date=YYYY-MM-DD&branch_id=..&user_id=..
     *
     * Fetch arrive/leave times for ONE patient on ONE day.
     * This uses the cached timeline table if you add it (recommended),
     * otherwise it can compute on demand by calling the month endpoint logic (heavy).
     *
     * This implementation computes on-demand for that day only.
     */
    public function patientTimeForDay(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'date' => 'required|date_format:Y-m-d',
            'branch_id' => 'required|integer|exists:branches,id',
            'user_id' => 'nullable|integer',
            'start_time' => 'nullable|date_format:H:i',
            'procedure_codes' => 'nullable|array',
            'procedure_codes.*' => 'string',
        ]);

        $tz = 'Europe/Bratislava';
        $userId = (int) ($data['user_id'] ?? Auth::id());
        $branchId = (int) $data['branch_id'];
        $patientId = (int) $data['patient_id'];
        $date = $data['date'];
        $startTimeHHmm = $data['start_time'] ?? '07:00';
        $procedureCodes = $data['procedure_codes'] ?? ['3439', '3440'];

        $branch = DB::table('branches')
            ->where('id', $branchId)
            ->select('id', 'latitude', 'longitude', 'per_location_time')
            ->first();

        if (!$branch || $branch->latitude === null || $branch->longitude === null) {
            return response()->json(['success' => false, 'message' => 'Invalid branch coords'], 422);
        }

        $perLocationSeconds = ((int) ($branch->per_location_time ?? 0)) * 60;
        if ($perLocationSeconds <= 0) $perLocationSeconds = 10 * 60;

        $visits = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->whereDate('pp.date', $date)
            ->when(!empty($procedureCodes), fn ($q) => $q->whereIn('pp.procedure_code', $procedureCodes))
            ->select([
                'pp.id as patient_point_id',
                'pp.date',
                'pp.patient_id',
                'p.first_name',
                'p.last_name',
                'p.city as patient_city',
                'p.address as patient_address',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
            ])
            ->orderBy('pp.id')
            ->get();

        $startUnix = Carbon::parse($date . ' ' . $startTimeHHmm . ':00', $tz)->timestamp;

        $timeline = $this->solveDayTimeline($date, $visits->all(), $branch, $startUnix, $perLocationSeconds);

        // Filter to requested patient (can have multiple stops that day)
        $patientStops = array_values(array_filter($timeline['stops'] ?? [], fn ($s) => (int)($s['patient_id'] ?? 0) === $patientId));

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'patient_id' => $patientId,
                'stops' => $patientStops, // each has arrive_unix/leave_unix
            ],
        ]);
    }

    /**
     * One day -> call solver -> parse stop timeline.
     * Each visit is a separate stop (even same coords). We make stops unique via tiny jitter.
     */
    private function solveDayTimeline(string $dateYmd, array $visits, object $branch, int $startUnix, int $perLocationSeconds): array
    {
        // Filter invalid coords but keep a stable stop order
        $validVisits = array_values(array_filter($visits, function ($v) {
            return $v->patient_lat !== null && $v->patient_lng !== null;
        }));

        if (!count($validVisits)) {
            return [
                'date' => $dateYmd,
                'stops' => [],
                'summary' => [
                    'start_unix' => $startUnix,
                    'end_unix' => $startUnix,
                    'total_travel_seconds' => 0,
                    'total_distance_m' => 0,
                ],
            ];
        }

        // Build points with deterministic micro-jitter so duplicates become unique
        $points = [];
        $timeSpending = [];
        $metaBySentKey = [];

        $baseEps = 0.000001; // tiny ~0.1m
        foreach ($validVisits as $i => $v) {
            $epsLat = $baseEps * (($i % 7) + 1);
            $epsLng = $baseEps * ((($i * 3) % 7) + 1);

            $latJ = (float)$v->patient_lat + $epsLat;
            $lngJ = (float)$v->patient_lng + $epsLng;

            $points[] = [$lngJ, $latJ]; // [lng, lat]
            $timeSpending[] = $perLocationSeconds;

            $sentKey = sprintf('%.7f,%.7f', $lngJ, $latJ);

            $metaBySentKey[$sentKey] = [
                'patient_point_id' => (int)$v->patient_point_id,
                'patient_id' => (int)$v->patient_id,
                'patient_name' => trim(($v->last_name ?? '') . ' ' . ($v->first_name ?? '')),
                'city' => (string)($v->patient_city ?? ''),
                'address' => (string)($v->patient_address ?? ''),
                'original_lat' => (float)$v->patient_lat,
                'original_lng' => (float)$v->patient_lng,
            ];
        }

        $payload = [
            'start_location' => [(float)$branch->longitude, (float)$branch->latitude],
            'end_location' => [(float)$branch->longitude, (float)$branch->latitude],
            'points_locations' => $points,
            'start_time' => $startUnix,
            'timeSpending' => $timeSpending,
        ];

        Log::info('TSP payload day', [
            'date' => $dateYmd,
            'points_count' => count($points),
            'start_time_unix' => $startUnix,
            'per_location_seconds' => $perLocationSeconds,
            'first_point' => $points[0] ?? null,
        ]);

        $json = $this->callTspSolver($payload, $dateYmd);
        if ($json === null) {
            return [
                'date' => $dateYmd,
                'error' => 'TSP solver call failed',
                'stops' => [],
            ];
        }

        $legs = data_get($json, 'response', []);
        if (!is_array($legs) || !count($legs)) {
            return [
                'date' => $dateYmd,
                'error' => 'TSP solver returned empty response',
                'stops' => [],
            ];
        }

        // Parse legs: last leg ends at branch; all previous legs end at a patient stop
        $stops = [];
        $totalTravel = 0.0;
        $totalDistance = 0.0;

        foreach ($legs as $i => $leg) {
            $totalTravel += (float)($leg['duration'] ?? 0);
            $totalDistance += (float)($leg['length'] ?? 0);

            $isLastLeg = ($i === count($legs) - 1);
            if ($isLastLeg) continue;

            $end = $leg['end'] ?? null; // [lng, lat]
            $ts  = $leg['timestamps'] ?? [];

            $sentKey = (is_array($end) && count($end) === 2)
                ? sprintf('%.7f,%.7f', (float)$end[0], (float)$end[1])
                : null;

            $meta = $sentKey ? ($metaBySentKey[$sentKey] ?? null) : null;

            $stops[] = [
                'patient_point_id' => $meta['patient_point_id'] ?? null,
                'patient_id' => $meta['patient_id'] ?? null,
                'patient_name' => $meta['patient_name'] ?? null,
                'city' => $meta['city'] ?? null,
                'address' => $meta['address'] ?? null,
                'original_lat' => $meta['original_lat'] ?? null,
                'original_lng' => $meta['original_lng'] ?? null,

                'leave_previous_unix' => (int) data_get($ts, 'leave_start_point', 0),
                'arrive_unix' => (int) data_get($ts, 'arrive_end_point', 0),
                'leave_unix' => (int) data_get($ts, 'leave_end_point', 0),

                'travel_seconds' => (float)($leg['duration'] ?? 0),
                'travel_distance_m' => (float)($leg['length'] ?? 0),
                'spent_seconds' => $perLocationSeconds,
            ];
        }

        $startAt = (int) data_get($legs, '0.timestamps.leave_start_point', $startUnix);
        $endAt   = (int) data_get($legs, (string)(count($legs)-1).'.timestamps.leave_end_point', $startAt);

        Log::info('TSP day parsed', [
            'date' => $dateYmd,
            'stops_count' => count($stops),
            'summary' => [
                'start_unix' => $startAt,
                'end_unix' => $endAt,
                'total_travel_seconds' => round($totalTravel, 2),
                'total_distance_m' => round($totalDistance, 2),
            ],
            'first_stop' => $stops[0] ?? null,
        ]);

        return [
            'date' => $dateYmd,
            'stops' => $stops,
            'summary' => [
                'start_unix' => $startAt,
                'end_unix' => $endAt,
                'total_travel_seconds' => round($totalTravel, 2),
                'total_distance_m' => round($totalDistance, 2),
            ],
        ];
    }

    private function callTspSolver(array $payload, string $dateYmd): ?array
    {
        try {
            $baseUrl  = rtrim(config('services.route_service.base_url'), '/');
            $endpoint = ltrim(config('services.route_service.endpoint', '/tsp-solver'), '/');
            $timeout  = (int) config('services.route_service.timeout', 12);

            $url = "{$baseUrl}/{$endpoint}";

            $resp = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode($payload), 'application/json')
                ->send('GET', $url);

            Log::info('TSP HTTP response', [
                'date' => $dateYmd,
                'status' => $resp->status(),
            ]);

            if (!$resp->successful()) {
                Log::warning('TSP HTTP failed', [
                    'date' => $dateYmd,
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
                return null;
            }

            return $resp->json();
        } catch (\Throwable $e) {
            Log::error('TSP call exception', [
                'date' => $dateYmd,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
