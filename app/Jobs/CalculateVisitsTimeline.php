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

    public $timeout = 600;
    public $tries = 3;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        $data = $this->data;

        $runId = bin2hex(random_bytes(6)); // short run id for log correlation
        $persist = (bool)($data['persist'] ?? true);
        $tz = 'Europe/Bratislava';

        $userId = (int)($data['user_id'] ?? 0);
        $branchId = (int)($data['branch_id'] ?? 0);
        $procedureCodes = $data['procedure_codes'] ?? ['3439', '3440'];
        $filterPatientIds = array_values(array_filter(array_map('intval', $data['patients'] ?? [])));

        if ($userId <= 0 || $branchId <= 0) {
            Log::error('CalculateVisitsTimeline: invalid input', [
                'run_id' => $runId,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'data_keys' => array_keys($data),
            ]);
            return;
        }

        $monthDate = Carbon::parse($data['month'] ?? now($tz))->toDateString();
        $month = Carbon::parse($monthDate, $tz);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        Log::info('CalculateVisitsTimeline: start', [
            'run_id' => $runId,
            'user_id' => $userId,
            'branch_id' => $branchId,
            'month' => $monthDate,
            'persist' => $persist,
            'from' => $from,
            'to' => $to,
            'procedure_codes' => $procedureCodes,
            'filter_patient_ids_count' => count($filterPatientIds),
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

            Log::info('CalculateVisitsTimeline: branch loaded', [
                'run_id' => $runId,
                'branch_lat' => (float)$branch->latitude,
                'branch_lng' => (float)$branch->longitude,
                'per_location_seconds' => $perLocationSeconds,
                'terrain_start' => $startTimeHHmm,
                'administrative_start' => $administrativeStartTimeHHmm,
            ]);

            $rows = DB::table('patient_points as pp')
                ->join('patients as p', 'p.id', '=', 'pp.patient_id')
                ->where('pp.user_id', $userId)
                ->where('pp.branch_id', $branchId)
                ->whereBetween('pp.date', [$from, $to])
                ->when(!empty($procedureCodes), fn($q) => $q->whereIn('pp.procedure_code', $procedureCodes))
                ->when(!empty($filterPatientIds), fn($q) => $q->whereIn('pp.patient_id', $filterPatientIds))
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

            Log::info('CalculateVisitsTimeline: patient_points loaded', [
                'run_id' => $runId,
                'rows' => $rows->count(),
            ]);

            $visitsByDay = [];
            foreach ($rows as $r) {
                $day = Carbon::parse($r->date, $tz)->toDateString();
                $visitsByDay[$day] ??= [];
                $visitsByDay[$day][] = $r;
            }

            $days = [];
            for ($d = 1; $d <= $month->daysInMonth; $d++) {
                $date = Carbon::create($month->year, $month->month, $d, 0, 0, 0, $tz)->toDateString();
                $startUnix = Carbon::parse($date . ' ' . $startTimeHHmm . ':00', $tz)->timestamp;

                $dayVisits = $visitsByDay[$date] ?? [];
                $timeline = $this->solveDayTimeline($runId, $date, $dayVisits, $branch, $startUnix, $perLocationSeconds);

                $days[] = $timeline;
            }

            if ($persist) {
                $inserted = $this->persistMonthTimelinesIntoVisits(
                    runId: $runId,
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

                Log::info('CalculateVisitsTimeline: persisted visits', [
                    'run_id' => $runId,
                    'inserted' => $inserted,
                ]);
            }

            DB::table('visit_calculations')->updateOrInsert(
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

            Log::info('CalculateVisitsTimeline: completed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'month' => $monthDate,
            ]);
        } catch (\Throwable $e) {
            Log::error('CalculateVisitsTimeline: Calculation failed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'month_date' => $monthDate,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            DB::table('visit_calculations')->updateOrInsert(
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
        string $runId,
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
            $runId,
            $from,
            $to,
            $userId,
            $branchId,
            $days,
            $tz,
            $now,
            $administrativeStartTimeHHmm
        ) {
            $deleted = DB::table('visits')
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->whereBetween('date', [$from, $to])
                ->delete();

            Log::info('CalculateVisitsTimeline: deleted existing visits', [
                'run_id' => $runId,
                'deleted' => $deleted,
                'from' => $from,
                'to' => $to,
                'user_id' => $userId,
                'branch_id' => $branchId,
            ]);

            $buffer = [];
            $inserted = 0;

            // Safety net: prevent duplicates inside this transaction even if solver/mapping goes wrong
            $seenKeys = [];

            foreach ($days as $day) {
                $date = $day['date'] ?? null;
                if (!$date) continue;

                $locations = $day['locations'] ?? [];

                $adminCursor = null;
                if ($administrativeStartTimeHHmm) {
                    $adminCursor = Carbon::parse($date . ' ' . $administrativeStartTimeHHmm . ':00', $tz);
                    $adminCursor->addMinutes(rand(-5, 5)); // small variation
                }

                foreach ($locations as $location) {
                    $arriveUnix = (int)($location['arrive_unix'] ?? 0);
                    if ($arriveUnix <= 0) continue;

                    $patientId = (int)($location['patient_id'] ?? 0);
                    $isReturnLeg = $patientId <= 0;

                    $uniqueKey = $isReturnLeg
                        ? $date . '|return|' . $userId . '|' . $branchId . '|' . $arriveUnix
                        : $date . '|' . $patientId . '|' . $userId . '|' . $branchId;
                    if (isset($seenKeys[$uniqueKey])) {
                        Log::warning('CalculateVisitsTimeline: duplicate visit row prevented before insert', [
                            'run_id' => $runId,
                            'date' => $date,
                            'patient_id' => $isReturnLeg ? null : $patientId,
                            'user_id' => $userId,
                            'branch_id' => $branchId,
                            'return_leg' => $isReturnLeg,
                        ]);
                        continue;
                    }
                    $seenKeys[$uniqueKey] = true;

                    $terrainTime = Carbon::createFromTimestamp($arriveUnix, $tz);
                    $administrativeTime = null;

                    if (!$isReturnLeg && $adminCursor) {
                        $paperSeconds = $this->paperworkSecondsForPatient($date, $patientId, $userId, $branchId);
                        $administrativeTime = $adminCursor->copy();
                        $adminCursor->addSeconds($paperSeconds);
                    }

                    $row = [
                        'date' => $date,
                        'patient_id' => $isReturnLeg ? null : $patientId,
                        'user_id' => $userId,
                        'branch_id' => $branchId,
                        'terrain_time' => $terrainTime->format('Y-m-d H:i:s'),
                        'administrative_time' => $administrativeTime?->format('Y-m-d H:i:s'),
                        'time_on_location' => 0,
                        'distance_to_location' => (int)round((float)($location['distance_km'] ?? 0) * 1000),
                        'time_to_location' => 0,
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

            return $inserted;
        });
    }

    private function solveDayTimeline(string $runId, string $dateYmd, array $visits, object $branch, int $startUnix, int $perLocationSeconds): array
    {
        // Keep only visits with coordinates
        $validVisits = array_values(array_filter($visits, fn($v) => $v->patient_lat !== null && $v->patient_lng !== null));

        if (!count($validVisits)) {
            return [
                'date' => $dateYmd,
                'locations' => [],
                'summary' => [
                    'start_unix' => $startUnix,
                    'end_unix' => $startUnix,
                    'total_travel_seconds' => 0,
                    'total_distance_m' => 0,
                ],
            ];
        }

        // Dedupe patient_id per day (in case patient_points has multiple entries for same patient/day)
        $seenPid = [];
        $deduped = [];
        foreach ($validVisits as $v) {
            $pid = (int)$v->patient_id;
            if ($pid <= 0) continue;
            if (isset($seenPid[$pid])) continue;
            $seenPid[$pid] = true;
            $deduped[] = $v;
        }
        $validVisits = $deduped;

        // Log same-coordinate groups (this is OK, but important to know)
        $coordGroups = [];
        foreach ($validVisits as $v) {
            $k = sprintf('%.7f,%.7f', (float)$v->patient_lng, (float)$v->patient_lat);
            $coordGroups[$k] = ($coordGroups[$k] ?? 0) + 1;
        }
        $dupeCoordGroups = array_filter($coordGroups, fn($c) => $c > 1);
        if ($dupeCoordGroups) {
            Log::warning('CalculateVisitsTimeline: multiple patients share same coordinates for day', [
                'run_id' => $runId,
                'date' => $dateYmd,
                'groups' => count($dupeCoordGroups),
                'examples' => array_slice($dupeCoordGroups, 0, 5, true),
            ]);
        }

        // Build jittered points list (NO associative map by rounded coords)
        $points = [];
        $timeSpending = [];
        $pointMeta = []; // indexed list aligned with $points

        $baseEps = 0.000001;

        foreach ($validVisits as $i => $v) {
            $epsLat = $baseEps * (($i % 7) + 1);
            $epsLng = $baseEps * ((($i * 3) % 7) + 1);

            $latJ = (float)$v->patient_lat + $epsLat;
            $lngJ = (float)$v->patient_lng + $epsLng;

            $points[] = [$lngJ, $latJ];
            $timeSpending[] = $perLocationSeconds;

            $pointMeta[] = [
                'patient_id' => (int)$v->patient_id,
                'latJ' => $latJ,
                'lngJ' => $lngJ,
                'used' => false,
            ];
        }

        Log::debug('CalculateVisitsTimeline: day points prepared', [
            'run_id' => $runId,
            'date' => $dateYmd,
            'valid_visits_count' => count($validVisits),
            'points_count' => count($points),
        ]);

        $payload = [
            'start_location' => [(float)$branch->longitude, (float)$branch->latitude],
            'end_location' => [(float)$branch->longitude, (float)$branch->latitude],
            'points_locations' => $points,
            'start_time' => $startUnix,
            'timeSpending' => $timeSpending,
        ];

        $json = $this->callTspSolver($payload, $dateYmd);
        if ($json === null) {
            return ['date' => $dateYmd, 'error' => 'TSP solver call failed', 'locations' => []];
        }

        $legs = data_get($json, 'response', []);
        if (!is_array($legs) || !count($legs)) {
            return ['date' => $dateYmd, 'error' => 'TSP solver returned empty response', 'locations' => []];
        }

        $locations = [];
        $unmatched = 0;

        foreach ($legs as $leg) {
            $end = $leg['end'] ?? null;
            $ts  = $leg['timestamps'] ?? [];

            if (!is_array($end) || count($end) !== 2) {
                continue;
            }

            $endLng = (float)$end[0];
            $endLat = (float)$end[1];

            // Find nearest unused point (handles identical original coordinates safely)
            $bestIdx = null;
            $bestDist2 = null;

            foreach ($pointMeta as $idx => $m) {
                if ($m['used']) continue;

                $dLat = $m['latJ'] - $endLat;
                $dLng = $m['lngJ'] - $endLng;
                $dist2 = ($dLat * $dLat) + ($dLng * $dLng);

                if ($bestDist2 === null || $dist2 < $bestDist2) {
                    $bestDist2 = $dist2;
                    $bestIdx = $idx;
                }
            }

            $patientId = null;

            // Tight threshold: jitter is around 1e-6, squared ~1e-12; allow a bit for solver float noise
            if ($bestIdx !== null && $bestDist2 !== null && $bestDist2 < 1e-8) {
                $patientId = $pointMeta[$bestIdx]['patient_id'];
                $pointMeta[$bestIdx]['used'] = true;
            } else {
                $unmatched++;
            }

            $locations[] = [
                'lat' => $endLat,
                'lng' => $endLng,
                'arrive_unix' => (int)data_get($ts, 'arrive_end_point', 0),
                'distance_km' => round((float)($leg['length'] ?? 0) / 1000, 2),
                'patient_id' => $patientId,
            ];
        }

        // Log duplicates (should not happen after nearest-unused matching)
        $pidCounts = [];
        foreach ($locations as $loc) {
            $pid = $loc['patient_id'] ?? null;
            if (!$pid) continue;
            $pidCounts[$pid] = ($pidCounts[$pid] ?? 0) + 1;
        }
        $dupes = array_filter($pidCounts, fn($c) => $c > 1);
        if ($dupes) {
            Log::warning('CalculateVisitsTimeline: duplicate patient_ids after matching (unexpected)', [
                'run_id' => $runId,
                'date' => $dateYmd,
                'duplicates' => $dupes,
                'locations_count' => count($locations),
                'valid_visits_count' => count($validVisits),
            ]);
        }

        if ($unmatched > 0) {
            Log::warning('CalculateVisitsTimeline: solver endpoints unmatched to input points', [
                'run_id' => $runId,
                'date' => $dateYmd,
                'unmatched' => $unmatched,
                'locations_count' => count($locations),
                'points_count' => count($points),
            ]);
        }

        return [
            'date' => $dateYmd,
            'locations' => $locations,
        ];
    }

    private function callTspSolver(array $payload, string $dateYmd): ?array
    {
        try {
            $baseUrl  = rtrim(config('services.route_service.base_url'), '/');
            $endpoint = ltrim(config('services.route_service.endpoint', '/tsp-solver'), '/');
            $timeout  = (int)config('services.route_service.timeout', 12);

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
                'exception' => get_class($e),
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
