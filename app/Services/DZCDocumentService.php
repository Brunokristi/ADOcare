<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Patient;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DZCDocumentService
{
    public function createDzc(array $data, $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $branch = Branch::findOrFail($data['branch_id']);

            $startDate = date('Y-m-d', strtotime($data['start']));
            $endDate = date('Y-m-d', strtotime($data['end']));
            $period = date('Y-m', strtotime($data['start']));

            $existing = Document::query()
                ->where('type', 'dzc')
                ->where('user_id', $actor->id)
                ->where('branch_id', $branch->id)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $newPath = 'dzcs/' . now()->timestamp . '.json';

            if ($existing) {
                if ($existing->path && Storage::disk('local')->exists($existing->path)) {
                    Storage::disk('local')->delete($existing->path);
                }

                $existing->update([
                    'mime_type' => 'application/json',
                    'name' => 'dzc_' . now()->format('d.m.Y'),
                    'path' => $newPath,
                    'period' => $period,
                ]);

                $document = $existing;
            } else {
                $document = Document::create([
                    'patient_id' => null,
                    'user_id' => $actor->id,
                    'type' => 'dzc',
                    'mime_type' => 'application/json',
                    'name' => 'dzc_' . now()->format('d.m.Y'),
                    'path' => $newPath,
                    'branch_id' => $branch->id,
                    'period' => $period,
                ]);
            }

            $user = $actor;
            $userId = $actor->id;
            $car = $user->cars()->first();

            $visitRows = DB::table('visits')
            ->leftJoin('patients', 'patients.id', '=', 'visits.patient_id')
            ->where('visits.user_id', $userId)
            ->where('visits.branch_id', $branch->id)
            ->whereBetween('visits.date', [$startDate, $endDate])
            ->orderBy('visits.date', 'ASC')
            ->orderByRaw('COALESCE(visits.terrain_time, visits.administrative_time) ASC')
            ->select([
                'visits.date',
                'visits.patient_id',
                'patients.address as patient_address',
                'patients.city as patient_city',
                'patients.latitude as patient_lat',
                'patients.longitude as patient_lng',
                'visits.terrain_time',
                'visits.administrative_time',
                'visits.distance_to_location',
                'visits.time_to_location',
                'visits.time_on_location',
            ])
            ->get();

            $visitsByDate = [];
            foreach ($visitRows as $r) {
            $d = $r->date;
            if (!isset($visitsByDate[$d])) $visitsByDate[$d] = [];
            $visitsByDate[$d][] = $r;
            }

            $patientAddresses = [];
            $branchAddress = trim(($branch->address ?? '') . ', ' . ($branch->city ?? ''));

            foreach ($visitsByDate as $date => $rows) {
            $day = [];
            $day[] = [
                'type' => 'branch_start',
                'address' => $branchAddress,
                'arrival_time' => date('Y-m-d H:i:s', strtotime($date . ' ' . $branch->terrain_start_time) + rand(-240, 240)),
                'kilometers' => 0,
            ];

            $lastPatient = null;
            $lastArrival = null;

            foreach ($rows as $r) {
                $arrival = $r->terrain_time ?? $r->administrative_time;
                if ($r->patient_id === null) {
                    continue;
                }

                $day[] = [
                    'type' => 'patient',
                    'patient_id' => (int)$r->patient_id,
                    'address' => (string)($r->patient_address ?? '') . ', ' . (string)($r->patient_city ?? ''),
                    'arrival_time' => $arrival,
                    'kilometers' => round(((int)($r->distance_to_location ?? 0)) / 1000, 2),
                ];
                
                // Track last patient for return leg calculation
                $lastPatient = $r;
                $lastArrival = $arrival;
            }

            // Calculate return leg from last patient to branch
            $returnKm = null;
            $returnTime = null;
            
            if ($lastPatient) {
                $patLat = (float)$lastPatient->patient_lat;
                $patLng = (float)$lastPatient->patient_lng;
                $branchLat = (float)$branch->latitude;
                $branchLng = (float)$branch->longitude;
                
                if ($patLat && $patLng && $branchLat && $branchLng) {
                    // Haversine distance
                    $returnKm = $this->haversineDistance($patLat, $patLng, $branchLat, $branchLng);
                    
                    // Estimate return time: ~60 km/h, plus 5 min variability
                    $returnSeconds = max(180, (int)($returnKm * 60));
                    $returnTime = date('Y-m-d H:i:s', strtotime($lastArrival) + $returnSeconds);
                } else {
                    // Fallback: use distance from last visit if available
                    $returnKm = $lastPatient->distance_to_location ? round(((int)$lastPatient->distance_to_location) / 1000, 2) : 0;
                    $returnTime = $lastArrival;
                }
            }

            $day[] = [
                'type' => 'branch_end',
                'address' => $branchAddress,
                'arrival_time' => $returnTime,
                'kilometers' => $returnKm,
            ];

            $patientAddresses[$date] = $day;
            }

            $dayRows = DB::table('visits')
            ->join('branches', 'branches.id', '=', 'visits.branch_id')
            ->where('visits.user_id', $userId)
            ->where('visits.branch_id', $branch->id)
            ->whereBetween('visits.date', [$startDate, $endDate])
            ->groupBy('visits.date')
            ->orderBy('visits.date')
            ->selectRaw('
                visits.date,
                COUNT(CASE WHEN visits.patient_id IS NOT NULL THEN 1 END) as stops,
                COALESCE(SUM(visits.time_to_location), 0) as travel_seconds,
                COALESCE(SUM(visits.distance_to_location), 0) as distance_m,
                MAX(branches.terrain_start_time) as terrain_start_time,
                MAX(COALESCE(visits.terrain_time, visits.administrative_time)) as last_arrival
            ')
            ->get();

            $dayTotals = [];
            foreach ($dayRows as $r) {
            $totalSeconds = 0;
            if ($r->terrain_start_time && $r->last_arrival) {
                $journeyDate = $r->date;
                $start = strtotime($journeyDate . ' ' . $r->terrain_start_time);
                $last = strtotime($r->last_arrival);
                $totalSeconds = max(0, $last - $start);
            }

            $hours = intval($totalSeconds / 3600);
            $minutes = intval(($totalSeconds % 3600) / 60);
            $seconds = intval($totalSeconds % 60);
            $totalTime = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

            $dayTotals[$r->date] = [
                'date' => $r->date,
                'stops' => (int)$r->stops,
                'distance_km' => round(((int)($r->distance_m) / 1000), 2),
                'total_time' => $totalTime,
            ];
            }

            $monthAgg = DB::table('visits')
            ->where('user_id', $userId)
            ->where('branch_id', $branch->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(CASE WHEN patient_id IS NOT NULL THEN 1 END) as stops,
                COALESCE(SUM(time_to_location), 0) as travel_seconds,
                COALESCE(SUM(distance_to_location), 0) as distance_m,
                MIN(date) as from_date,
                MAX(date) as to_date
            ')
            ->first();

            $monthTotals = [
            'from' => $monthAgg?->from_date ?? $startDate,
            'to' => $monthAgg?->to_date ?? $endDate,
            'distance_km' => round(((int)($monthAgg->distance_m ?? 0)) / 1000, 2),
            ];

            $dzcData = [
            'user_id' => $userId,
            'user_name' => trim(($user->title ? $user->title . ' ' : '') . $user->first_name . ' ' . $user->last_name),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'trip_purpose' => 'Návšteva pacienta',
            'month' => date('m', strtotime($startDate)),
            'year' => date('Y', strtotime($startDate)),
            'car_model' => $car?->model ?? '',
            'car_license_plate' => $car?->evc ?? '',
            'car_consumption_l_per_100km' => $car?->fuel_consumption_l_per_100km,
            'branch_address' => $branchAddress ?? '',
            'patient_addresses' => $patientAddresses,
            'day_totals' => $dayTotals,
            'month_totals' => $monthTotals,
            'document_id' => $document->id,
            'created_at' => now()->toISOString(),
            ];

            try {
                $disk = Storage::disk('local');
                $directory = dirname($document->path);
            
                // Ensure directory exists
                if (!$disk->exists($directory)) {
                    $disk->makeDirectory($directory, 0755, true);
                    \Log::info('Created DZC directory', ['directory' => $directory]);
                }
            
                $disk->put($document->path, json_encode($dzcData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
                // Verify file was actually saved
                if (!$disk->exists($document->path)) {
                    throw new \Exception('File was not saved to disk');
                }
            
                \Log::info('DZC file created successfully', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                    'full_path' => $disk->path($document->path),
                    'file_size' => $disk->size($document->path)
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to save DZC file', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            return [$document, $dzcData];
        });
    }

    public function createManagerDzcFromVisitLocations(array $data, $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $branch = Branch::with('company')->findOrFail($data['branch_id']);
            $periodCarbon = Carbon::createFromFormat('Y-m', $data['period']);
            $startDate = $periodCarbon->copy()->startOfMonth()->toDateString();
            $endDate = $periodCarbon->copy()->endOfMonth()->toDateString();
            $period = $periodCarbon->format('Y-m');

            $rawLocations = collect($branch->company?->visit_locations ?? [])
                ->map(function ($item) {
                    return [
                        'address' => trim((string)($item['address'] ?? '')),
                        'street' => trim((string)($item['street'] ?? '')),
                        'city' => trim((string)($item['city'] ?? '')),
                        'zip' => trim((string)($item['zip'] ?? '')),
                        'latitude' => is_numeric($item['latitude'] ?? null) ? (float)$item['latitude'] : null,
                        'longitude' => is_numeric($item['longitude'] ?? null) ? (float)$item['longitude'] : null,
                    ];
                })
                ->filter(function ($item) {
                    return $item['address'] !== '' || $item['street'] !== '' || $item['city'] !== '';
                })
                ->values();

            $routableLocations = $rawLocations
                ->filter(function ($item) {
                    return $item['latitude'] !== null && $item['longitude'] !== null;
                })
                ->values();

            if ($rawLocations->isEmpty()) {
                throw new \InvalidArgumentException('Spoločnosť nemá uložené lokality návštev.');
            }

            $existing = Document::query()
                ->where('type', 'dzc')
                ->where('user_id', $actor->id)
                ->where('branch_id', $branch->id)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $newPath = 'dzcs/' . now()->timestamp . '.json';

            if ($existing) {
                if ($existing->path && Storage::disk('local')->exists($existing->path)) {
                    Storage::disk('local')->delete($existing->path);
                }

                $existing->update([
                    'mime_type' => 'application/json',
                    'name' => 'dzc_' . now()->format('d.m.Y'),
                    'path' => $newPath,
                    'period' => $period,
                ]);

                $document = $existing;
            } else {
                $document = Document::create([
                    'patient_id' => null,
                    'user_id' => $actor->id,
                    'type' => 'dzc',
                    'mime_type' => 'application/json',
                    'name' => 'dzc_' . now()->format('d.m.Y'),
                    'path' => $newPath,
                    'branch_id' => $branch->id,
                    'period' => $period,
                ]);
            }

            $branchAddress = trim(implode(', ', array_filter([$branch->address, $branch->city])));
            $branchLat = is_numeric($branch->latitude) ? (float)$branch->latitude : null;
            $branchLng = is_numeric($branch->longitude) ? (float)$branch->longitude : null;
            $terrainStart = $branch->terrain_start_time ?: '08:00:00';

            $patientAddresses = [];
            $dayTotals = [];
            $monthDistanceKm = 0.0;
            $holidaySet = $this->getSlovakHolidayDateSet((int) $periodCarbon->format('Y'));

            $cursor = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            while ($cursor->lte($end)) {
                if ($cursor->isWeekday() && !isset($holidaySet[$cursor->toDateString()])) {
                    $date = $cursor->toDateString();
                    $day = [];

                    $dayStart = Carbon::parse($date . ' ' . $terrainStart)->addMinutes(random_int(-4, 4));
                    $day[] = [
                        'type' => 'branch_start',
                        'address' => $branchAddress,
                        'arrival_time' => $dayStart->format('Y-m-d H:i:s'),
                        'kilometers' => 0,
                    ];

                    $locationPool = $routableLocations->isNotEmpty() ? $routableLocations : $rawLocations;
                    $maxStops = min(3, max(1, $locationPool->count()));
                    $stopsCount = random_int(1, $maxStops);
                    $selectedStops = $locationPool->shuffle()->take($stopsCount)->values();

                    $currentLat = $branchLat;
                    $currentLng = $branchLng;
                    $dayDistanceKm = 0.0;
                    $timeCursor = $dayStart->copy();

                    $serviceRoute = null;
                    if ($branchLat !== null && $branchLng !== null) {
                        $serviceRoute = $this->solveManagerDayWithRouteService(
                            date: $date,
                            dayStart: $dayStart,
                            branchLat: $branchLat,
                            branchLng: $branchLng,
                            selectedStops: $selectedStops->all()
                        );
                    }

                    if ($serviceRoute !== null) {
                        foreach ($serviceRoute['stops'] as $serviceStop) {
                            $day[] = [
                                'type' => 'patient',
                                'patient_id' => null,
                                'address' => $serviceStop['address'],
                                'arrival_time' => $serviceStop['arrival_time'],
                                'kilometers' => $serviceStop['kilometers'],
                            ];
                        }

                        $day[] = [
                            'type' => 'branch_end',
                            'address' => $branchAddress,
                            'arrival_time' => $serviceRoute['return']['arrival_time'],
                            'kilometers' => $serviceRoute['return']['kilometers'],
                        ];

                        $dayDistanceKm = (float) $serviceRoute['distance_km'];
                        $timeCursor = Carbon::parse($serviceRoute['return']['arrival_time']);
                    } else {
                        foreach ($selectedStops as $stop) {
                            $stopAddress = $this->formatVisitLocationAddress($stop);

                            $legKm = null;
                            if ($currentLat !== null && $currentLng !== null && $stop['latitude'] !== null && $stop['longitude'] !== null) {
                                $legKm = $this->haversineDistance($currentLat, $currentLng, $stop['latitude'], $stop['longitude']);
                            }

                            if ($legKm === null || $legKm <= 0) {
                                $legKm = (float) random_int(2, 15);
                            }

                            $travelMinutes = max(5, (int) round(($legKm / 45) * 60) + random_int(0, 8));
                            $timeCursor->addMinutes($travelMinutes);

                            $day[] = [
                                'type' => 'patient',
                                'patient_id' => null,
                                'address' => $stopAddress,
                                'arrival_time' => $timeCursor->format('Y-m-d H:i:s'),
                                'kilometers' => round($legKm, 2),
                            ];

                            $dayDistanceKm += $legKm;
                            $timeCursor->addMinutes(random_int(20, 45));

                            $currentLat = $stop['latitude'];
                            $currentLng = $stop['longitude'];
                        }

                        $returnKm = null;
                        if ($currentLat !== null && $currentLng !== null && $branchLat !== null && $branchLng !== null) {
                            $returnKm = $this->haversineDistance($currentLat, $currentLng, $branchLat, $branchLng);
                        }
                        if ($returnKm === null || $returnKm <= 0) {
                            $returnKm = (float) random_int(2, 12);
                        }

                        $returnMinutes = max(5, (int) round(($returnKm / 45) * 60) + random_int(0, 8));
                        $timeCursor->addMinutes($returnMinutes);

                        $day[] = [
                            'type' => 'branch_end',
                            'address' => $branchAddress,
                            'arrival_time' => $timeCursor->format('Y-m-d H:i:s'),
                            'kilometers' => round($returnKm, 2),
                        ];

                        $dayDistanceKm += $returnKm;
                    }
                    $patientAddresses[$date] = $day;

                    $totalSeconds = max(0, $timeCursor->diffInSeconds($dayStart));
                    $hours = intdiv($totalSeconds, 3600);
                    $minutes = intdiv($totalSeconds % 3600, 60);
                    $seconds = $totalSeconds % 60;

                    $dayTotals[$date] = [
                        'date' => $date,
                        'stops' => $stopsCount,
                        'distance_km' => round($dayDistanceKm, 2),
                        'total_time' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
                    ];

                    $monthDistanceKm += $dayDistanceKm;
                }

                $cursor->addDay();
            }

            $car = $actor->cars()->first();
            $dzcData = [
                'user_id' => $actor->id,
                'user_name' => trim(($actor->title ? $actor->title . ' ' : '') . $actor->first_name . ' ' . $actor->last_name),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'trip_purpose' => 'Pracovné stretnutia',
                'month' => $periodCarbon->format('m'),
                'year' => $periodCarbon->format('Y'),
                'car_model' => $car?->model ?? '',
                'car_license_plate' => $car?->evc ?? '',
                'car_consumption_l_per_100km' => $car?->fuel_consumption_l_per_100km,
                'branch_address' => $branchAddress,
                'patient_addresses' => $patientAddresses,
                'day_totals' => $dayTotals,
                'month_totals' => [
                    'from' => $startDate,
                    'to' => $endDate,
                    'distance_km' => round($monthDistanceKm, 2),
                ],
                'document_id' => $document->id,
                'created_at' => now()->toISOString(),
            ];

            Storage::disk('local')->put($document->path, json_encode($dzcData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return [$document, $dzcData];
        });
    }

    private function getSlovakHolidayDateSet(int $year): array
    {
        $set = [];

        $fixed = [
            [$year, 1, 1],
            [$year, 1, 6],
            [$year, 5, 1],
            [$year, 5, 8],
            [$year, 7, 5],
            [$year, 8, 29],
            [$year, 9, 1],
            [$year, 9, 15],
            [$year, 11, 1],
            [$year, 11, 17],
            [$year, 12, 24],
            [$year, 12, 25],
            [$year, 12, 26],
        ];

        foreach ($fixed as [$y, $m, $d]) {
            $set[Carbon::create($y, $m, $d)->toDateString()] = true;
        }

        $easterSunday = $this->getEasterSunday($year);
        $set[$easterSunday->copy()->subDays(2)->toDateString()] = true; // Good Friday
        $set[$easterSunday->copy()->addDay()->toDateString()] = true; // Easter Monday

        return $set;
    }

    private function getEasterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }

    private function solveManagerDayWithRouteService(
        string $date,
        Carbon $dayStart,
        float $branchLat,
        float $branchLng,
        array $selectedStops
    ): ?array {
        if (empty($selectedStops)) {
            return null;
        }

        $points = [];
        $timeSpending = [];
        $pointMeta = [];
        $baseEps = 0.000001;

        foreach (array_values($selectedStops) as $i => $stop) {
            if (!isset($stop['latitude'], $stop['longitude']) || $stop['latitude'] === null || $stop['longitude'] === null) {
                return null;
            }

            $epsLat = $baseEps * (($i % 7) + 1);
            $epsLng = $baseEps * (((($i * 3) % 7)) + 1);

            $latJ = (float)$stop['latitude'] + $epsLat;
            $lngJ = (float)$stop['longitude'] + $epsLng;

            $points[] = [$lngJ, $latJ];
            $timeSpending[] = random_int(20, 45) * 60;

            $pointMeta[] = [
                'latJ' => $latJ,
                'lngJ' => $lngJ,
                'address' => $this->formatVisitLocationAddress($stop),
                'used' => false,
            ];
        }

        $payload = [
            'start_location' => [$branchLng, $branchLat],
            'end_location' => [$branchLng, $branchLat],
            'points_locations' => $points,
            'start_time' => $dayStart->timestamp,
            'timeSpending' => $timeSpending,
        ];

        $response = $this->callTspSolver($payload, $date);
        if ($response === null) {
            return null;
        }

        $legs = data_get($response, 'response', []);
        if (!is_array($legs) || !count($legs)) {
            return null;
        }

        $tz = config('app.timezone', 'Europe/Bratislava');
        $stops = [];
        $returnLeg = null;
        $distanceKm = 0.0;

        foreach ($legs as $leg) {
            $legKm = round((float)($leg['length'] ?? 0) / 1000, 2);
            $distanceKm += $legKm;

            $arriveUnix = (int)data_get($leg, 'timestamps.arrive_end_point', 0);
            $arriveAt = $arriveUnix > 0
                ? Carbon::createFromTimestamp($arriveUnix, $tz)
                : $dayStart->copy();

            $end = $leg['end'] ?? null;
            $isMatchedStop = false;

            if (is_array($end) && count($end) === 2) {
                $endLng = (float)$end[0];
                $endLat = (float)$end[1];

                $bestIdx = null;
                $bestDist2 = null;
                foreach ($pointMeta as $idx => $meta) {
                    if ($meta['used']) {
                        continue;
                    }

                    $dLat = $meta['latJ'] - $endLat;
                    $dLng = $meta['lngJ'] - $endLng;
                    $dist2 = ($dLat * $dLat) + ($dLng * $dLng);

                    if ($bestDist2 === null || $dist2 < $bestDist2) {
                        $bestDist2 = $dist2;
                        $bestIdx = $idx;
                    }
                }

                if ($bestIdx !== null && $bestDist2 !== null && $bestDist2 < 1e-8) {
                    $pointMeta[$bestIdx]['used'] = true;
                    $stops[] = [
                        'address' => $pointMeta[$bestIdx]['address'],
                        'arrival_time' => $arriveAt->format('Y-m-d H:i:s'),
                        'kilometers' => $legKm,
                    ];
                    $isMatchedStop = true;
                }
            }

            if (!$isMatchedStop) {
                $returnLeg = [
                    'arrival_time' => $arriveAt->format('Y-m-d H:i:s'),
                    'kilometers' => $legKm,
                ];
            }
        }

        if ($returnLeg === null) {
            return null;
        }

        return [
            'stops' => $stops,
            'return' => $returnLeg,
            'distance_km' => round($distanceKm, 2),
        ];
    }

    private function callTspSolver(array $payload, string $date): ?array
    {
        try {
            $baseUrl = rtrim((string)config('services.route_service.base_url'), '/');
            $endpoint = ltrim((string)config('services.route_service.endpoint', '/tsp-solver'), '/');
            $timeout = (int)config('services.route_service.timeout', 12);

            if ($baseUrl === '') {
                return null;
            }

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode($payload), 'application/json')
                ->send('GET', $baseUrl . '/' . $endpoint);

            if (!$response->successful()) {
                \Log::warning('Manager DZC route_service call failed', [
                    'date' => $date,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            \Log::error('Manager DZC route_service exception', [
                'date' => $date,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return null;
        }
    }

    private function formatVisitLocationAddress(array $stop): string
    {
        $address = trim((string)($stop['address'] ?? ''));
        $street = trim((string)($stop['street'] ?? ''));
        $city = trim((string)($stop['city'] ?? ''));
        $zip = trim((string)($stop['zip'] ?? ''));

        $base = $address !== '' ? $address : trim(implode(' ', array_filter([$street, $city])));
        return trim(implode(', ', array_filter([$base, $zip])));
    }

    public function getDzcPayload(Document $document): ?array
    {
        if (!$document->path) {
            \Log::error('DZC document has no path', ['document_id' => $document->id]);
            return null;
        }

        $disk = Storage::disk('local');
        $fullPath = $disk->path($document->path);
        
        if (!$disk->exists($document->path)) {
            \Log::error('DZC file not found', [
                'document_id' => $document->id,
                'stored_path' => $document->path,
                'full_path' => $fullPath,
                'disk_root' => $disk->path(''),
                'directory_exists' => is_dir(dirname($fullPath)),
                'files_in_dir' => is_dir(dirname($fullPath)) ? scandir(dirname($fullPath)) : 'N/A'
            ]);
            return null;
        }

        try {
            $content = $disk->get($document->path);
            
            if (empty($content)) {
                \Log::error('DZC file is empty', ['document_id' => $document->id, 'path' => $document->path]);
                return null;
            }
            
            $payload = json_decode($content, true);
            
            if (!$payload) {
                \Log::error('DZC file contains invalid JSON', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                    'json_error' => json_last_error_msg()
                ]);
                return null;
            }
            
            \Log::info('DZC file retrieved successfully', [
                'document_id' => $document->id,
                'path' => $document->path,
                'payload_keys' => array_keys($payload)
            ]);
            
            return $payload;
        } catch (\Exception $e) {
            \Log::error('Error reading DZC file', [
                'document_id' => $document->id,
                'path' => $document->path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Calculate distance between two points using Haversine formula (in km).
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }
}
