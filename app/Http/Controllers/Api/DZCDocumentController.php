<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DZCDocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::where('type', 'dzc')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        if ($request->has('branch_id')) {
            // For DZC documents, we can filter by the branch_id from request
            // Since DZC documents don't have a branch_id column, we'll just return all for the user
            // But we can add filtering if needed
        }

        $documents = $query->get()->map(function ($doc) {
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'type' => $doc->type,
                'mime_type' => $doc->mime_type,
                'created_at' => $doc->created_at,
                'path' => $doc->path,
            ];
        });

        return response()->json(['data' => $documents]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = Auth::user();
        $userId = (int) Auth::id();

        $branchId = (int) $validated['branch_id'];
        $branch = Branch::findOrFail($branchId);

        // normalize to Y-m-d
        $startDate = date('Y-m-d', strtotime($validated['start']));
        $endDate   = date('Y-m-d', strtotime($validated['end']));

        $month = date('m', strtotime($startDate));
        $year  = date('Y', strtotime($startDate));

        $car = $user->cars()->first();

        $userName = trim(($user->title ? $user->title . ' ' : '') . $user->first_name . ' ' . $user->last_name);
        $carModel = $car ? (string) $car->model : '';
        $carLicensePlate = $car ? (string) $car->evc : '';

        $branchAddress = (string) ($branch->address ?? '' ) . ', ' . (string) ($branch->city ?? '');
        $branchLatitude = (float) ($branch->latitude ?? 0);
        $branchLongitude = (float) ($branch->longitude ?? 0);

        $document = Document::create([
            'patient_id' => null,
            'user_id' => $userId,
            'type' => 'dzc',
            'mime_type' => 'application/json',
            'name' => 'dzc_' . now()->format('d.m.Y'),
            'path' => 'dzcs/' . 'dzc_' . now()->timestamp . '.json',
        ]);

        $visitRows = DB::table('visits')
            ->leftJoin('patients', 'patients.id', '=', 'visits.patient_id')
            ->where('visits.user_id', $userId)
            ->where('visits.branch_id', $branchId)
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

        foreach ($visitsByDate as $date => $rows) {
            $day = [];

            // Always start at branch
            $day[] = [
                'type' => 'branch_start',
                'address' => $branchAddress,
                'arrival_time' => date('Y-m-d H:i:s', strtotime($date . ' ' . $branch->terrain_start_time) + rand(-240, 240)),
                'kilometers' => 0,
            ];

            $lastReturnKm = null;
            $lastReturnTime = null;

            foreach ($rows as $r) {
                $arrival = $r->terrain_time ?? $r->administrative_time;

                // Return-to-branch row (patient_id null)
                if ($r->patient_id === null) {
                    $lastReturnKm = round(((int)($r->distance_to_location ?? 0)) / 1000, 2);
                    $lastReturnTime = $arrival;
                    continue;
                }

                // Patient row
                $day[] = [
                    'type' => 'patient',
                    'patient_id' => (int)$r->patient_id,
                    'address' => (string)($r->patient_address ?? '') . ', ' . (string)($r->patient_city ?? ''),
                    'arrival_time' => $arrival,
                    'kilometers' => round(((int)($r->distance_to_location ?? 0)) / 1000, 2),
                ];
            }

            $day[] = [
                'type' => 'branch_end',
                'address' => $branchAddress,
                'arrival_time' => $lastReturnTime,
                'kilometers' => $lastReturnKm,
            ];

            $patientAddresses[$date] = $day;
        }

        $dayRows = DB::table('visits')
            ->join('branches', 'branches.id', '=', 'visits.branch_id')
            ->where('visits.user_id', $userId)
            ->where('visits.branch_id', $branchId)
            ->whereBetween('visits.date', [$startDate, $endDate])
            ->groupBy('visits.date')
            ->orderBy('visits.date')
            ->selectRaw('
                visits.date,
                COUNT(*) as stops,
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
                'distance_km' => round(((int)$r->distance_m) / 1000, 2),
                'total_time' => $totalTime,
            ];
        }

        $monthAgg = DB::table('visits')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as stops,
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

        /**
         * 5) Build JSON snapshot
         */
        $dzcData = [
            'user_id' => $userId,
            'user_name' => $userName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'month' => $month,
            'year' => $year,
            'car_model' => $carModel,
            'car_license_plate' => $carLicensePlate,
            'branch_address' => $branchAddress,

            // Timeline with per-stop time + km
            'patient_addresses' => $patientAddresses,

            // Totals (already include return leg if you persisted it)
            'day_totals' => $dayTotals,
            'month_totals' => $monthTotals,

            'document_id' => $document->id,
            'created_at' => now()->toISOString(),
        ];

        // 6) Save JSON to the exact path stored on Document
        Storage::disk('local')->put(
            $document->path,
            json_encode($dzcData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Denný záznam ciest bol úspešne vytvorený',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['user'])->findOrFail($documentId);

        $path = (string) $document->path;

        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Denný záznam ciest data not found'], 404);
        }

        $dzcFile = json_decode(Storage::disk('local')->get($path), true);

        return response()->json([
            'document' => $document,
            'dzc_data' => $dzcFile,
        ]);
    }

    public function exportCsv($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['user'])->findOrFail($documentId);

        $path = (string) $document->path;

        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Denný záznam ciest data not found'], 404);
        }

        $dzcFile = json_decode(Storage::disk('local')->get($path), true);

        // Generate CSV
        $csv = $this->generateDZCCsv($dzcFile);

        $filename = 'dzc_' . $dzcFile['month'] . '_' . $dzcFile['year'] . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function generateDZCCsv(array $dzcFile): string
    {
        $output = fopen('php://memory', 'w');
        
        // Header info
        fputcsv($output, ['DENNÝ ZÁZNAM CIEST']);
        fputcsv($output, []);
        fputcsv($output, ['Pracovník', $dzcFile['user_name'] ?? '']);
        fputcsv($output, ['Obdobie', ($dzcFile['month'] ?? '') . '/' . ($dzcFile['year'] ?? '')]);
        fputcsv($output, ['Vozidlo', ($dzcFile['car_model'] ?? '') . ' (' . ($dzcFile['car_license_plate'] ?? '') . ')']);
        fputcsv($output, ['Prevádzka', $dzcFile['branch_address'] ?? '']);
        fputcsv($output, []);

        // Daily records
        fputcsv($output, ['Dátum', 'Počet km', 'Trvanie','Poradové číslo', 'Príchod', 'Adresa']);
        
        $patientAddresses = $dzcFile['patient_addresses'] ?? [];
        $dayTotals = $dzcFile['day_totals'] ?? [];

        foreach ($patientAddresses as $date => $addresses) {
            $dayTotal = $dayTotals[$date] ?? null;
            
            foreach ($addresses as $idx => $addr) {
                $row = [];
                
                if ($idx === 0) {
                    $row[] = $date;
                    $row[] = $dayTotal['distance_km'] ?? '';
                    $row[] = $dayTotal['total_time'] ?? '';

                } else {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                }
                
                // Address info
                $row[] = $idx + 1;
                $row[] = $addr['arrival_time'] ?? '';
                $row[] = $addr['address'] ?? '';
                
                fputcsv($output, $row);
            }
        }

        fputcsv($output, []);
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
