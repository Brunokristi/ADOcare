<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Patient;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
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
