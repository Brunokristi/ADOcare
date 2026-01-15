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
use App\Http\Controllers\Api\VisitsController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;


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
            'branch_id' => 'required|integer|exists:branches,id',
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

        // normalize sections
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
        $dailyTexts = $this->buildDailyTexts($sections);
        $neededDates = array_keys($dailyTexts);

        if ($month) {
            $this->ensureMonthTimelineExistsOrCreate(
                monthYmd: $month,
                branchId: (int)$validated['branch_id'],
                userId: (int)Auth::id(),
                patientId: (int)$patient->id,
                neededDates: $neededDates
            );
        }

        // Now times should exist (or be best-effort), attach them:
        $daysWithTimes = $this->attachVisitTimesForPatient(
            dailyTexts: $dailyTexts,
            patientId: (int)$patient->id,
            userId: (int)Auth::id(),
            branchId: (int)$validated['branch_id']
        );


        // ✅ attach terrain + administrative times (if available in visits table)
        $daysWithTimes = $this->attachVisitTimesForPatient(
            dailyTexts: $dailyTexts,
            patientId: (int)$patient->id,
            userId: (int)Auth::id(),
            branchId: (int)$validated['branch_id']
        );

        Log::info('Dekurz daily texts combined', [
            'document_id' => $document->id,
            'patient_id' => $patient->id,
            'branch_id' => (int)$validated['branch_id'],
            'days_count' => count($daysWithTimes),
            'sample' => array_slice($daysWithTimes, 0, 2),
        ]);

        $dekurzData = [
            'document_id' => $document->id,
            'created_at' => now(),
            'user_id' => Auth::id(),

            'patient_id' => $patient->id,
            'patient_name' => trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')),
            'dekurz_number' => $validated['dekurz_number'],
            'month' => $month,

            // keep original sections if you still want them
            'sections' => $sections,

            // ✅ new: per-day merged text + times
            'days' => $daysWithTimes,
        ];

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

    public function last(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
        ]);

        $doc = Document::query()
            ->where('type', 'dekurz')
            ->where('patient_id', (int)$data['patient_id'])
            ->orderByDesc('id')
            ->first();

        if (!$doc) {
            return response()->json(['success' => true, 'data' => null]);
        }

        if (!$doc->path || !Storage::disk('local')->exists($doc->path)) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $json = json_decode(Storage::disk('local')->get($doc->path), true);

        return response()->json([
            'success' => true,
            'data' => [
                'document_id' => $doc->id,
                'sections' => $json['sections'] ?? [],
            ],
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

    private function buildDailyTexts(array $sections): array
    {
        // Returns: ['2026-01-01' => "combined text...", ...]
        $byDate = [];

        foreach ($sections as $s) {
            $text = trim((string)($s['text'] ?? ''));
            $dates = $s['dates'] ?? [];

            if ($text === '' || !is_array($dates) || !count($dates)) {
                continue;
            }

            foreach ($dates as $d) {
                $date = Carbon::parse($d)->toDateString();

                // append in a nice way
                if (!isset($byDate[$date])) {
                    $byDate[$date] = $text;
                } else {
                    // separate blocks cleanly
                    $byDate[$date] .= "\n\n" . $text;
                }
            }
        }

        // normalize whitespace a bit
        foreach ($byDate as $date => $txt) {
            $byDate[$date] = preg_replace("/\n{3,}/", "\n\n", trim($txt));
        }

        ksort($byDate);

        return $byDate;
    }

    private function attachVisitTimesForPatient(array $dailyTexts, int $patientId, int $userId, int $branchId): array
    {
        if (!$branchId) {
            // no branch => cannot match visits reliably
            return array_map(fn ($text, $date) => [
                'date' => $date,
                'text' => $text,
                'terrain_time' => null,
                'administrative_time' => null,
            ], $dailyTexts, array_keys($dailyTexts));
        }

        $dates = array_keys($dailyTexts);

        $rows = DB::table('visits')
            ->where('patient_id', $patientId)
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->whereIn('date', $dates)
            ->select(['date', 'terrain_time', 'administrative_time'])
            ->get()
            ->keyBy('date');

        $out = [];
        foreach ($dailyTexts as $date => $text) {
            $row = $rows[$date] ?? null;

            $out[] = [
                'date' => $date,
                'text' => $text,

                // if missing (timeline not generated), these will be null
                'terrain_time' => $row->terrain_time ?? null,
                'administrative_time' => $row->administrative_time ?? null,
            ];
        }

        return $out;
    }

    private function ensureMonthTimelineExistsOrCreate(
        string $monthYmd,
        int $branchId,
        int $userId,
        int $patientId,
        array $neededDates // array of 'Y-m-d'
    ): void {
        $tz = 'Europe/Bratislava';

        $month = Carbon::parse($monthYmd)->setTimezone($tz);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        // If no dates needed, skip
        $neededDates = array_values(array_unique(array_filter($neededDates)));
        if (!count($neededDates)) {
            return;
        }

        // Check if we already have ALL needed dates for this patient with both times filled
        $existingDates = DB::table('visits')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('patient_id', $patientId)
            ->whereIn('date', $neededDates)
            ->whereNotNull('terrain_time')
            ->whereNotNull('administrative_time')
            ->distinct()
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $missingDates = array_values(array_diff($neededDates, $existingDates));

        Log::info('Dekurz timeline check', [
            'month' => $month->format('Y-m'),
            'from' => $from,
            'to' => $to,
            'user_id' => $userId,
            'branch_id' => $branchId,
            'patient_id' => $patientId,
            'needed_dates_count' => count($neededDates),
            'existing_dates_count' => count($existingDates),
            'missing_dates_count' => count($missingDates),
            'missing_dates_sample' => array_slice($missingDates, 0, 10),
        ]);

        if (!count($missingDates)) {
            return; // all good
        }

        // ✅ Recalculate whole month timeline (persist to DB)
        // We'll call your VisitsController method directly (no HTTP roundtrip).
        $req = Request::create('/v1/visits/timeline', 'POST', [
            'month' => $month->toDateString(),
            'branch_id' => $branchId,
            'user_id' => $userId,
            'persist' => true,
        ]);

        // Make sure Auth::id() inside controller isn't needed (we pass user_id anyway)
        $controller = app(VisitsController::class);
        $resp = $controller->monthTimeline($req);

        Log::info('Dekurz triggered month timeline recalculation', [
            'month' => $month->format('Y-m'),
            'user_id' => $userId,
            'branch_id' => $branchId,
            'http_status' => method_exists($resp, 'status') ? $resp->status() : null,
        ]);
    }


}
