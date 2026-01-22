<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CalculateVisitsTimeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes timeout
    public $tries = 3;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        $data = $this->data;
        $persist = (bool)($data['persist'] ?? true);
        $tz = 'Europe/Bratislava';

        $userId = (int) ($data['user_id'] ?? null);
        $branchId = (int) $data['branch_id'];
        $procedureCodes = $data['procedure_codes'] ?? ['3439', '3440'];
        $filterPatientIds = array_values(array_filter(array_map('intval', $data['patients'] ?? [])));

        // Parse month the same way the controller does to ensure consistent format
        $monthDate = Carbon::parse($data['month'])->toDateString(); // Normalize to YYYY-MM-DD
        $month = Carbon::parse($monthDate)->setTimezone($tz);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        Log::info('CalculateVisitsTimeline: Job started', [
            'user_id' => $userId,
            'branch_id' => $branchId,
            'month_date' => $monthDate,
            'month_raw_input' => $data['month'],
        ]);

        try {
            $branch = DB::table('branches')
                ->where('id', $branchId)
                ->select('id', 'latitude', 'longitude', 'per_location_time', 'terrain_start_time', 'administrative_start_time')
                ->first();

            if (!$branch) {
                throw new \Exception('Branch not found');
            }

            if ($branch->latitude === null || $branch->longitude === null) {
                throw new \Exception('Branch has missing coordinates (latitude/longitude).');
            }

            $perLocationSeconds = ((int)($branch->per_location_time ?? 0)) * 60;
            if ($perLocationSeconds <= 0) {
                $perLocationSeconds = 10 * 60;
            }

            $startTimeHHmm = $branch->terrain_start_time
                ? substr((string)$branch->terrain_start_time, 0, 5)
                : '08:00';

            $administrativeStartTimeHHmm = $branch->administrative_start_time
                ? substr((string)$branch->administrative_start_time, 0, 5)
                : null;

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

            Log::info('CalculateVisitsTimeline: Data loaded', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
                'rows' => $rows->count(),
            ]);

            $visitsByDay = [];
            foreach ($rows as $r) {
                $day = Carbon::parse($r->date)->toDateString();
                $visitsByDay[$day] ??= [];
                $visitsByDay[$day][] = $r;
            }

            $days = [];
            for ($d = 1; $d <= $month->daysInMonth; $d++) {
                $date = Carbon::create($month->year, $month->month, $d, 0, 0, 0, $tz)->toDateString();
                $startUnix = Carbon::parse($date . ' ' . $startTimeHHmm . ':00', $tz)->timestamp;

                $dayVisits = $visitsByDay[$date] ?? [];
                $timeline = $this->solveDayTimeline($date, $dayVisits, $branch, $startUnix, $perLocationSeconds);

                $days[] = $timeline;
            }

            if ($persist) {
                $this->persistMonthTimelinesIntoVisits(
                    from: $from,
                    to: $to,
                    userId: $userId,
                    branchId: $branchId,
                    startTimeHHmm: $startTimeHHmm,
                    administrativeStartTimeHHmm: $administrativeStartTimeHHmm,
                    branch: $branch,
                    days: $days,
                    tz: $tz,
                    perLocationSeconds: $perLocationSeconds
                );
            }

            // Mark calculation as completed
            Log::info('CalculateVisitsTimeline: About to update database', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'month_date' => $monthDate,
            ]);

            // Debug: Check what's in the database before update
            $existingRecord = DB::table('visit_calculations')
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->where('month', $monthDate)
                ->first();
            
            Log::info('CalculateVisitsTimeline: Before updateOrInsert', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'month_date' => $monthDate,
                'month_date_type' => gettype($monthDate),
                'existing_record_found' => $existingRecord ? true : false,
                'existing_month' => $existingRecord?->month,
                'existing_status' => $existingRecord?->status,
            ]);

            $updateResult = DB::table('visit_calculations')
                ->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'branch_id' => $branchId,
                        'month' => $monthDate,
                    ],
                    [
                        'status' => 'completed',
                        'completed_at' => now(),
                        'error_message' => null,
                    ]
                );

            Log::info('CalculateVisitsTimeline: Database updated successfully', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'month_date' => $monthDate,
                'update_result' => $updateResult,
            ]);

            Log::info('CalculateVisitsTimeline: Calculation completed', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            Log::error('CalculateVisitsTimeline: Calculation failed', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'month_date' => $monthDate,
                'error' => $e->getMessage(),
            ]);

            // Mark calculation as failed
            DB::table('visit_calculations')
                ->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'branch_id' => $branchId,
                        'month' => $monthDate,
                    ],
                    [
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]
                );

            throw $e;
        }
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
            $deleted = DB::table('visits')
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->whereBetween('date', [$from, $to])
                ->delete();

            Log::info('CalculateVisitsTimeline persist: deleted old rows', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'deleted' => $deleted,
            ]);

            $buffer = [];
            $inserted = 0;

            foreach ($days as $day) {
                $date = $day['date'] ?? null;
                if (!$date) continue;

                $stops = $day['stops'] ?? [];
                if (!is_array($stops) || !count($stops)) continue;

                usort($stops, fn ($a, $b) => ((int)($a['arrive_unix'] ?? 0)) <=> ((int)($b['arrive_unix'] ?? 0)));

                $adminCursor = null;
                if ($administrativeStartTimeHHmm) {
                    $adminCursor = Carbon::parse($date . ' ' . $administrativeStartTimeHHmm . ':00', $tz);
                }

                foreach ($stops as $s) {
                    $patientId = (int)($s['patient_id'] ?? 0);
                    $arriveUnix = (int)($s['arrive_unix'] ?? 0);

                    if ($patientId <= 0 || $arriveUnix <= 0) {
                        continue;
                    }

                    $terrainTime = Carbon::createFromTimestamp($arriveUnix, $tz);
                    $administrativeTime = null;

                    if ($adminCursor) {
                        $paperSeconds = $this->paperworkSecondsForPatient($date, $patientId, $userId, $branchId);
                        $administrativeTime = $adminCursor->copy();
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

            Log::info('CalculateVisitsTimeline persist: inserted rows', [
                'user_id' => $userId,
                'branch_id' => $branchId,
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

        $baseEps = 0.000001;

        foreach ($validVisits as $i => $v) {
            $epsLat = $baseEps * (($i % 7) + 1);
            $epsLng = $baseEps * ((($i * 3) % 7) + 1);

            $latJ = (float)$v->patient_lat + $epsLat;
            $lngJ = (float)$v->patient_lng + $epsLng;

            $points[] = [$lngJ, $latJ];
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

            $end = $leg['end'] ?? null;
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
                Log::warning('TSP HTTP failed in job', [
                    'date' => $dateYmd,
                    'status' => $resp->status(),
                ]);
                return null;
            }

            return $resp->json();
        } catch (\Throwable $e) {
            Log::error('TSP call exception in job', [
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
