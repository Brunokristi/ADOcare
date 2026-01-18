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

        // Use authenticated user ID if not provided
        if (!isset($data['user_id']) || !$data['user_id']) {
            $data['user_id'] = Auth::id();
        }

        // Create initial record with pending status
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

        // Dispatch the job to the queue
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

    private function persistMonthTimelinesIntoVisits(
        string $from,
        string $to,
        int $userId,
        int $branchId,
        string $startTimeHHmm,
        ?string $administrativeStartTimeHHmm,
        object $branch,
        array $days,
        string $tz,
        int $perLocationSeconds
    ): int {
        $now = now()->setTimezone($tz)->format('Y-m-d H:i:s');

        return DB::transaction(function () use (
            $from,
            $to,
            $userId,
            $branchId,
            $days,
            $tz,
            $now,
            $perLocationSeconds,
            $administrativeStartTimeHHmm
        ) {
            // 1) delete existing month rows
            $deleted = DB::table('visits')
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->whereBetween('date', [$from, $to])
                ->delete();

            Log::info('Visits persist: deleted old month rows', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
                'deleted' => $deleted,
            ]);

            $buffer = [];
            $inserted = 0;

            foreach ($days as $day) {
                $date = $day['date'] ?? null;
                if (!$date) continue;

                $stops = $day['stops'] ?? [];
                if (!is_array($stops) || !count($stops)) continue;

                // Sort by actual visit order (terrain arrival)
                usort($stops, fn ($a, $b) => ((int)($a['arrive_unix'] ?? 0)) <=> ((int)($b['arrive_unix'] ?? 0)));

                // 2) build afternoon paperwork timeline cursor
                $adminCursor = null;
                if ($administrativeStartTimeHHmm) {
                    $adminCursor = Carbon::parse($date . ' ' . $administrativeStartTimeHHmm . ':00', $tz);
                }

                Log::info('Visits persist: day start', [
                    'date' => $date,
                    'stops' => count($stops),
                    'admin_start' => $administrativeStartTimeHHmm,
                    'admin_cursor' => $adminCursor?->format('Y-m-d H:i:s'),
                ]);

                foreach ($stops as $s) {
                    $patientId = (int)($s['patient_id'] ?? 0);
                    $arriveUnix = (int)($s['arrive_unix'] ?? 0);

                    if ($patientId <= 0 || $arriveUnix <= 0) {
                        continue;
                    }

                    // Terrain arrival time (from solver)
                    $terrainTime = Carbon::createFromTimestamp($arriveUnix, $tz);

                    // Administrative paperwork time in afternoon (sequential)
                    $administrativeTime = null;

                    if ($adminCursor) {
                        // paperwork duration 3..6 min
                        $paperSeconds = $this->paperworkSecondsForPatient($date, $patientId, $userId, $branchId);

                        // store the time when papers for that patient are done/recorded
                        // If you want "start time", keep as-is.
                        // If you want "finish time", store $adminCursor->copy()->addSeconds($paperSeconds) instead.
                        $administrativeTime = $adminCursor->copy();

                        // advance cursor for next patient
                        $adminCursor->addSeconds($paperSeconds);
                    }

                    $row = [
                        'date' => $date,
                        'patient_id' => $patientId,
                        'user_id' => $userId,
                        'branch_id' => $branchId,

                        'terrain_time' => $terrainTime->format('Y-m-d H:i:s'),
                        'administrative_time' => $administrativeTime?->format('Y-m-d H:i:s'),

                        'time_on_location' => (int)($s['spent_seconds'] ?? $perLocationSeconds),
                        'distance_to_location' => (int)round((float)($s['travel_distance_m'] ?? 0)),
                        'time_to_location' => (int)round((float)($s['travel_seconds'] ?? 0)),

                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $buffer[] = $row;

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

            Log::info('Visits persist: inserted rows', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
                'inserted' => $inserted,
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

    private function paperworkSecondsForPatient(string $dateYmd, int $patientId, int $userId, int $branchId): int
    {
        $seed = crc32($dateYmd . '|' . $patientId . '|' . $userId . '|' . $branchId);
        return 180 + ($seed % 181);
    }
}
