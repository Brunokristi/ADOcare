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
    public function monthTimeline(Request $request)
    {
        set_time_limit(300);

        $data = $request->validate([
            'month' => 'required|date',
            'branch_id' => 'required|integer|exists:branches,id',
            'user_id' => 'nullable|integer',
            'start_time' => 'nullable|date_format:H:i', // default 07:00
            'procedure_codes' => 'nullable|array',
            'procedure_codes.*' => 'string',
            'patients' => 'nullable|array',
            'patients.*' => 'integer',

            // if true -> delete & reinsert in DB
            'persist' => 'nullable|boolean',
        ]);

        $persist = (bool)($data['persist'] ?? true);

        $tz = 'Europe/Bratislava';

        $userId = (int) ($data['user_id'] ?? Auth::id());
        $branchId = (int) $data['branch_id'];
        $startTimeHHmm = $data['start_time'] ?? '07:00';
        $procedureCodes = $data['procedure_codes'] ?? ['3439', '3440'];
        $filterPatientIds = array_values(array_filter(array_map('intval', $data['patients'] ?? [])));

        $month = Carbon::parse($data['month'])->setTimezone($tz);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        $branch = DB::table('branches')
            ->where('id', $branchId)
            ->select('id', 'latitude', 'longitude', 'per_location_time', 'terrain_start_time', 'administrative_start_time')
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

        $perLocationSeconds = ((int)($branch->per_location_time ?? 0)) * 60;
        if ($perLocationSeconds <= 0) {
            $perLocationSeconds = 10 * 60; // fallback
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
            'persist' => $persist,
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

        // ✅ Persist into visits table (delete + insert)
        $inserted = 0;
        if ($persist) {
            $inserted = $this->persistMonthTimelinesIntoVisits(
                from: $from,
                to: $to,
                userId: $userId,
                branchId: $branchId,
                startTimeHHmm: $startTimeHHmm,
                branch: $branch,
                days: $days,
                tz: $tz,
                perLocationSeconds: $perLocationSeconds
            );
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
                    'persisted' => $persist,
                    'inserted_rows' => $inserted,
                ],
                'days' => $days,
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

    private function persistMonthTimelinesIntoVisits(
        string $from,
        string $to,
        int $userId,
        int $branchId,
        string $startTimeHHmm,
        object $branch,
        array $days,
        string $tz,
        int $perLocationSeconds
    ): int {
        // Decide which column we fill based on start_time.
        // If the request start_time matches branch start time fields, we store accordingly.
        $startTimeHHmm = substr($startTimeHHmm, 0, 5);
        $terrainStart = isset($branch->terrain_start_time) ? substr((string)$branch->terrain_start_time, 0, 5) : null;
        $adminStart   = isset($branch->administrative_start_time) ? substr((string)$branch->administrative_start_time, 0, 5) : null;

        $mode = 'terrain'; // default
        if ($adminStart && $startTimeHHmm === $adminStart) $mode = 'administrative';
        if ($terrainStart && $startTimeHHmm === $terrainStart) $mode = 'terrain';

        $now = now()->setTimezone($tz)->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($from, $to, $userId, $branchId, $days, $tz, $now, $mode, $perLocationSeconds) {
            // Delete old rows
            $deleted = DB::table('visits')
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->whereBetween('date', [$from, $to])
                ->delete();

            Log::info('Visits timeline delete before insert', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
                'deleted' => $deleted,
                'mode' => $mode,
            ]);

            $buffer = [];
            $inserted = 0;

            foreach ($days as $day) {
                $date = $day['date'] ?? null;
                if (!$date) continue;

                $stops = $day['stops'] ?? [];
                if (!is_array($stops) || !count($stops)) continue;

                foreach ($stops as $s) {
                    $patientId = (int)($s['patient_id'] ?? 0);
                    if ($patientId <= 0) continue;

                    $arriveUnix = (int)($s['arrive_unix'] ?? 0);
                    if ($arriveUnix <= 0) continue;

                    $arriveTs = Carbon::createFromTimestamp($arriveUnix, $tz)->format('Y-m-d H:i:s');

                    $row = [
                        'date' => $date,
                        'patient_id' => $patientId,
                        'user_id' => $userId,
                        'branch_id' => $branchId,

                        // Fill exactly one of these based on mode
                        'terrain_time' => $mode === 'terrain' ? $arriveTs : null,
                        'administrative_time' => $mode === 'administrative' ? $arriveTs : null,

                        'time_on_location' => (int)($s['spent_seconds'] ?? $perLocationSeconds),
                        'distance_to_location' => (int)round((float)($s['travel_distance_m'] ?? 0)),
                        'time_to_location' => (int)round((float)($s['travel_seconds'] ?? 0)),

                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $buffer[] = $row;

                    // bulk insert in chunks to avoid huge single query
                    if (count($buffer) >= 1000) {
                        DB::table('visits')->insert($buffer);
                        $inserted += count($buffer);
                        $buffer = [];
                    }
                }
            }

            if (count($buffer)) {
                DB::table('visits')->insert($buffer);
                $inserted += count($buffer);
            }

            Log::info('Visits timeline inserted', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
                'inserted' => $inserted,
                'mode' => $mode,
            ]);

            return $inserted;
        });
    }

    private function solveDayTimeline(string $dateYmd, array $visits, object $branch, int $startUnix, int $perLocationSeconds): array
    {
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

        $points = [];
        $timeSpending = [];
        $metaBySentKey = [];

        $baseEps = 0.000001; // tiny jitter to keep stops unique even at same coords

        foreach ($validVisits as $i => $v) {
            $epsLat = $baseEps * (($i % 7) + 1);
            $epsLng = $baseEps * ((($i * 3) % 7) + 1);

            $latJ = (float)$v->patient_lat + $epsLat;
            $lngJ = (float)$v->patient_lng + $epsLng;

            $points[] = [$lngJ, $latJ]; // [lng, lat]
            $timeSpending[] = $perLocationSeconds;

            $sentKey = sprintf('%.7f,%.7f', $lngJ, $latJ);

            $metaBySentKey[$sentKey] = [
                'patient_id' => (int)$v->patient_id,
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
        ]);

        $json = $this->callTspSolver($payload, $dateYmd);
        if ($json === null) {
            return ['date' => $dateYmd, 'error' => 'TSP solver call failed', 'stops' => []];
        }

        $legs = data_get($json, 'response', []);
        if (!is_array($legs) || !count($legs)) {
            return ['date' => $dateYmd, 'error' => 'TSP solver returned empty response', 'stops' => []];
        }

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
                'patient_id' => $meta['patient_id'] ?? null,
                'arrive_unix' => (int) data_get($ts, 'arrive_end_point', 0),
                'leave_unix' => (int) data_get($ts, 'leave_end_point', 0),
                'leave_previous_unix' => (int) data_get($ts, 'leave_start_point', 0),

                'travel_seconds' => (float)($leg['duration'] ?? 0),
                'travel_distance_m' => (float)($leg['length'] ?? 0),
                'spent_seconds' => $perLocationSeconds,
            ];
        }

        $startAt = (int) data_get($legs, '0.timestamps.leave_start_point', $startUnix);
        $endAt   = (int) data_get($legs, (string)(count($legs)-1).'.timestamps.leave_end_point', $startAt);

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
