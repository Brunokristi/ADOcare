<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\PatientPoint;



class DekurzDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dekurz_number' => 'required|string|max:50',
            'month' => 'nullable|date',
            'sections' => 'required|array|min:1',
            'sections.*.text' => 'required|string',
            'sections.*.dates' => 'required|array|min:1',
            'sections.*.dates.*' => 'required|date_format:Y-m-d',
            'branch_id' => 'integer',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);

        $document = Document::create([
            'patient_id' => $patient->id,
            'user_id' => Auth::id(),
            'type' => 'dekurz',
            'mime_type' => 'application/json',
            'name' => 'dekurz_' . now()->format('d.m.Y'),
            'path' => 'dekurz/' . 'dekurz_' . now()->timestamp . '.json',
        ]);

        $sections = collect($validated['sections'])->map(function ($s) {
            $dates = collect($s['dates'])
                ->map(fn ($d) => date('Y-m-d', strtotime($d)))
                ->unique()
                ->sort()
                ->values()
                ->all();

            return [
                'text' => (string) $s['text'],
                'dates' => $dates,
            ];
        })->values()->all();

        $month = $validated['month'] ?? null;

        if ($month) {
        $visitedAddresses = $this->buildMonthVisitedAddresses([
            'month' => $month,
            'user_id' => Auth::id(),
            'branch_id' => $validated['branch_id'] ?? null,
            'insurance_id' => $patient->insurance_company_id ?? null,
            'patient_ids' => [$patient->id],
        ]);

        Log::info('Dekurz month visited addresses', [
            'document_id' => $document->id,
            'patient_id' => $patient->id,
            'month' => $month,
            'meta' => $visitedAddresses['meta'],
            // careful: this can be large
            'days_sample' => array_slice($visitedAddresses['days'], 0, 3),
        ]);

        // optionally store it in the JSON file
        $dekurzData['visited_addresses'] = $visitedAddresses;
    }

        $dekurzData = [
            'document_id' => $document->id,
            'created_at' => now(),
            'user_id' => Auth::id(),

            'patient_id' => $patient->id,
            'patient_name' => trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')),
            'dekurz_number' => $validated['dekurz_number'],
            'month' => $month,

            'sections' => $sections,
        ];

        Log::info('Creating Dekurz Document', [
            'document_id' => $document->id,
            'patient_id' => $patient->id,
            'dekurz_number' => $validated['dekurz_number'],
            'sections_count' => count($sections),
        ]);

        Storage::disk('local')->put(
            $document->path,
            json_encode($dekurzData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Dekurz bol úspešne vytvorený',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;

        $document = Document::with(['user', 'patient'])->findOrFail($documentId);

        $dekurzFile = null;

        // Fast path: if the path exists on the document
        if ($document->path && Storage::disk('local')->exists($document->path)) {
            $dekurzFile = json_decode(Storage::disk('local')->get($document->path), true);
        } else {
            // Fallback path scan (like your CP controller)
            $files = Storage::disk('local')->files('dekurz');
            foreach ($files as $file) {
                $content = json_decode(Storage::disk('local')->get($file), true);
                if (($content['document_id'] ?? null) === $documentId) {
                    $dekurzFile = $content;
                    break;
                }
            }
        }

        if (!$dekurzFile) {
            return response()->json(['message' => 'Dekurz data not found'], 404);
        }

        return response()->json([
            'document' => $document,
            'dekurz_data' => $dekurzFile,
        ]);
    }

    public function availableDates(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'month'      => 'required|date',
        ]);

        $month = Carbon::parse($data['month'])->setTimezone('Europe/Bratislava');
        $from  = $month->copy()->startOfMonth()->toDateString();
        $to    = $month->copy()->endOfMonth()->toDateString();

        $dates = PatientPoint::query()
            ->where('patient_id', (int) $data['patient_id'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->distinct()
            ->pluck('date');

        $isoDates = $dates
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $days = $isoDates
            ->map(fn ($d) => (int) Carbon::parse($d)->day)
            ->unique()
            ->sort()
            ->values();

        Log::info('Fetched available Dekurz dates', [
            'patient_id' => (int) $data['patient_id'],
            'month'      => $month->toDateString(),
            'from'       => $from,
            'to'         => $to,
            'dates_count'=> $isoDates->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Available dates retrieved',
            'data' => [
                'month_from' => $from,
                'month_to'   => $to,
                'dates'      => $isoDates,
                'days'       => $days,
            ],
        ]);
    }

    private function buildMonthVisitedAddresses(array $params): array
    {
        $tz = 'Europe/Bratislava';

        $month = Carbon::parse($params['month'])->setTimezone($tz);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();
        $userId   = (int) $params['user_id'];
        $branchId = (int) $params['branch_id'];

        $branch = Branch::findOrFail($branchId);
        $branchLong = $branch->longitude;
        $branchLat = $branch->latitude;
        $startTime = $branch->start_time ?? '08:00:00';
        $perLocationTime = $branch->per_location_time ?? 10;

        $rows = \DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->whereBetween('pp.date', [$from, $to])
            ->whereIn('pp.procedure_code', ['3439', '3440'])
            ->select([
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
            ->get();

        $calendar = [];
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = Carbon::create($month->year, $month->month, $day, 0, 0, 0, $tz)->toDateString();
            $calendar[$date] = [
                'date' => $date,
                'addresses' => [],
                '_addrIndex' => [],
            ];
        }

        foreach ($rows as $r) {
            $date = Carbon::parse($r->date)->toDateString();
            if (!isset($calendar[$date])) continue;

            $lat = $r->patient_lat;
            $lng = $r->patient_lng;

            $addressKey = ($lat !== null && $lng !== null)
                ? "{$lat},{$lng}"
                : "{$city}|{$addr}";

            if (!isset($calendar[$date]['_addrIndex'][$addressKey])) {
                $calendar[$date]['addresses'][] = [
                    'key' => $addressKey,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'patients' => [],
                    '_seenPatients' => [],
                ];
                $calendar[$date]['_addrIndex'][$addressKey] =
                    count($calendar[$date]['addresses']) - 1;
            }

            $idx = $calendar[$date]['_addrIndex'][$addressKey];
            $pid = (int) $r->patient_id;

            if (isset($calendar[$date]['addresses'][$idx]['_seenPatients'][$pid])) {
                continue;
            }

            $calendar[$date]['addresses'][$idx]['_seenPatients'][$pid] = true;

            $calendar[$date]['addresses'][$idx]['patients'][] = [
                'patient_id' => $pid,
                'patient_name' => trim("{$r->last_name} {$r->first_name}"),
            ];
        }

        $days = [];
        foreach ($calendar as $bucket) {
            foreach ($bucket['addresses'] as &$a) {
                unset($a['_seenPatients']);
            }
            unset($bucket['_addrIndex']);
            $days[] = $bucket;
        }

        return [
            'meta' => [
                'month' => $month->format('Y-m'),
                'from' => $from,
                'to' => $to,
                'rows' => $rows->count(),
            ],
            'days' => $days,
        ];
    }
}
