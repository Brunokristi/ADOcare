<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class KilometersExportController extends Controller
{
    public function preview(Request $request)
    {
        $data = $request->validate([
            'batchNumber' => 'required|integer',
            'batchType.code' => 'required|string',
            'insurance.id' => 'required|integer',
            'period' => 'required|array|size:2',
            'period.*' => 'required|date',

            'user.id' => 'required|integer',
            'branch.id' => 'required|integer',
            'company.id' => 'required|integer',

            'patients' => 'array',
            'patients.*.id' => 'integer',
        ]);

        $from = Carbon::parse($data['period'][0])->setTimezone('Europe/Bratislava')->toDateString();
        $to = Carbon::parse($data['period'][1])->setTimezone('Europe/Bratislava')->toDateString();

        $userId = (int) $data['user']['id'];
        $branchId = (int) $data['branch']['id'];
        $companyId = (int) $data['company']['id'];
        $insuranceId = (int) $data['insurance']['id'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->values()
            ->all();

        $amount = 0.0;
        $totalKilometers = 0.0;

        $rows = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('branches as b', 'b.id', '=', 'pp.branch_id')
            ->join('procedures as proc', function ($join) {
                $join->where('proc.code', '0000');
            })
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'proc.id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->whereIn('pp.procedure_code', ['3439', '3440'])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->select([
                'pp.id',
                'pp.date',
                'b.latitude as branch_lat',
                'b.longitude as branch_lng',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
                'pcp.price'
            ])
            ->orderBy('pp.date')
            ->get();

        Log::info('Kilometers preview: rows fetched', [
            'count' => $rows->count(),
            'userId' => $userId,
            'branchId' => $branchId,
            'insuranceId' => $insuranceId,
            'from' => $from,
            'to' => $to,
            'patientIdsCount' => count($patientIds),
        ]);

        // If empty, nothing will be calculated.
        if ($rows->isEmpty()) {
            Log::warning('Kilometers preview: no rows matched query');
        }

        $visitedAddressesPerDay = [];
        $skippedMissingCoords = 0;
        $calledRouteService = 0;

        foreach ($rows as $row) {
            // ✅ Fix: do NOT use truthy checks (0 is falsy). Check null explicitly.
            if (
                $row->branch_lat === null || $row->branch_lng === null ||
                $row->patient_lat === null || $row->patient_lng === null
            ) {
                $skippedMissingCoords++;
                Log::warning('Skipping row due to missing coords', [
                    'pp_id' => $row->id,
                    'branch_lat' => $row->branch_lat,
                    'branch_lng' => $row->branch_lng,
                    'patient_lat' => $row->patient_lat,
                    'patient_lng' => $row->patient_lng,
                ]);
                continue;
            }

            $dateString = is_string($row->date)
                ? $row->date
                : (method_exists($row->date, 'toDateString') ? $row->date->toDateString() : 'unknown');

            $patientAddressKey = "{$row->patient_lat},{$row->patient_lng}";
            $visitedAddressesPerDay[$dateString] ??= [];

            if (in_array($patientAddressKey, $visitedAddressesPerDay[$dateString], true)) {
                $distanceKm = 0.0;
            } else {
                $calledRouteService++;

                Log::info('Calling route service for distance', [
                    'pp_id' => $row->id,
                    'date' => $dateString,
                    'from' => [(float) $row->branch_lng, (float) $row->branch_lat],
                    'to' => [(float) $row->patient_lng, (float) $row->patient_lat],
                ]);

                $distanceKm = $this->getDistanceFromRouteService(
                    (float) $row->branch_lat,
                    (float) $row->branch_lng,
                    (float) $row->patient_lat,
                    (float) $row->patient_lng
                );

                $visitedAddressesPerDay[$dateString][] = $patientAddressKey;
            }

            $totalKilometers += $distanceKm;
            $amount += $distanceKm * (float) $row->price;
        }

        Log::info('Kilometers preview: loop summary', [
            'skippedMissingCoords' => $skippedMissingCoords,
            'calledRouteService' => $calledRouteService,
            'totalKilometers' => $totalKilometers,
            'amount' => $amount,
        ]);

        $companyName = DB::table('company')->where('id', $companyId)->value('name');
        $branchName = DB::table('branches')
            ->where('id', $branchId)
            ->selectRaw("TRIM(CONCAT(COALESCE(city,''), ', ', COALESCE(address,''))) as name")
            ->value('name');

        $performedBy = DB::table('users')
            ->where('id', $userId)
            ->selectRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")
            ->value('name');

        $insuranceName = DB::table('insurance_companies')
            ->where('id', $insuranceId)
            ->value('name');

        $sheet = [
            'batchNumber' => $data['batchNumber'],
            'fileName' => "davka.{$data['batchNumber']}.txt",
            'amount' => (string) $amount,
            'kilometers' => round($totalKilometers, 2),
            'periodFrom' => $from,
            'periodTo' => $to,
            'performedBy' => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->setTimezone('Europe/Bratislava')->toDateString(),
            'companyName' => $companyName,
            'branchName' => $branchName,
            'patients' => $patientIds,
            'insuranceName' => $insuranceName,
        ];

        Log::info('Preview generated', ['sheet' => $sheet]);

        return response()->json([
            'success' => true,
            'message' => 'Preview generated',
            'data' => ['sheet' => $sheet],
        ]);
    }


    public function download(Request $request)
    {
        $data = $request->validate([
            'batchNumber' => 'required|integer',
            'batchType.code' => 'required|string|in:N,O',
            'insurance.id' => 'required|integer',
            'period' => 'required|array|size:2',
            'period.*' => 'required|date',

            'user.id' => 'required|integer',
            'branch.id' => 'required|integer',
            'company.id' => 'required|integer',

            'patients' => 'array',
            'patients.*.id' => 'integer',
        ]);

        $from = Carbon::parse($data['period'][0])->setTimezone('Europe/Bratislava')->toDateString();
        $to = Carbon::parse($data['period'][1])->setTimezone('Europe/Bratislava')->toDateString();

        $type = $data['batchType']['code']; // N or O
        $batchNumber = (int) $data['batchNumber'];
        $userId = (int) $data['user']['id'];
        $branchId = (int) $data['branch']['id'];
        $companyId = (int) $data['company']['id'];
        $insuranceId = (int) $data['insurance']['id'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $company = DB::table('company')
            ->where('id', $companyId)
            ->select('id', 'ico', 'name')
            ->first();

        $branch = DB::table('branches')
            ->where('id', $branchId)
            ->select('id', 'code', 'identificator')
            ->first();

        $user = DB::table('users')
            ->where('id', $userId)
            ->select('id', 'code')
            ->first();

        $workingTime = DB::table('user_branches')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->value('working_time');

        $workingTime = $workingTime ?? 0;

        $insuranceBranchCode = DB::table('insurance_companies')
            ->where('id', $insuranceId)
            ->value('branch_code');

        $userCar = DB::table('cars')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->value('evc') ?? '0';

        $termYYYYMM = Carbon::parse($from)->format('Ym');
        $generatedYmd = now()->setTimezone('Europe/Bratislava')->format('Ymd');

        $rows = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('doctors as d', 'd.id', '=', 'p.doctor_id')
            ->join('branches as b', 'b.id', '=', 'pp.branch_id')
            // replaced pivot join with direct patient fields (nurse_id, branch_id)
            ->whereColumn('p.nurse_id', 'pp.user_id')
            ->whereColumn('p.branch_id', 'pp.branch_id')
            ->join('procedures as proc', function ($join) {
                $join->where('proc.code', '0000');
            })
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'proc.id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->whereIn('pp.procedure_code', ['3439', '3440'])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->orderBy('pp.date')
            ->select([
                'pp.date',
                'pp.patient_id',
                'p.personal_number',
                'p.last_name',
                'p.first_name',
                'p.sex',
                'p.city as patient_city',
                'p.address as patient_address',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
                'pp.diagnosis_code',
                'pp.procedure_code',
                'pp.quantity',
                'd.pzs as doctor_pzs',
                'd.zpr as doctor_zpr',
                'b.city as branch_city',
                'b.address as branch_address',
                'b.latitude as branch_lat',
                'b.longitude as branch_lng',
                'pcp.price'
            ])
            ->get();


        $visitedAddressesPerDay = [];
        $kilometersPerRow = [];

        foreach ($rows as $idx => $row) {
            if (!$this->hasValidCoords($row->branch_lat, $row->branch_lng, $row->patient_lat, $row->patient_lng)) {
                $kilometersPerRow[$idx] = 0.0;
                continue;
            }

            $dateString = is_string($row->date) ? $row->date : (method_exists($row->date, 'toDateString') ? $row->date->toDateString() : 'unknown');
            $patientAddressKey = "{$row->patient_lat},{$row->patient_lng}";

            $visitedAddressesPerDay[$dateString] ??= [];

            if (in_array($patientAddressKey, $visitedAddressesPerDay[$dateString], true)) {
                $kilometersPerRow[$idx] = 0.0;
                continue;
            }

            $km = $this->getDistanceFromRouteService(
                $row->branch_lat,
                $row->branch_lng,
                $row->patient_lat,
                $row->patient_lng
            );

            $visitedAddressesPerDay[$dateString][] = $patientAddressKey;
            $kilometersPerRow[$idx] = $km;
        }

        $rowCount = $rows->count();

        $line1 = implode('|', [
            $type,
            '793n',
            $company->ico ?? '',
            $generatedYmd,
            $batchNumber,
            $rowCount,
            '1',
            '1',
            $insuranceBranchCode ?? '',
            ''
        ]);

        $line2 = implode('|', [
            $branch->identificator ?? '',
            $branch->code ?? '',
            $user->code ?? '',
            number_format((float) $workingTime, 2, '.', ''),
            $termYYYYMM,
            $batchNumber,
            'EUR',
            ''
        ]);

        $dataLines = [];
        $i = 1;

        foreach ($rows as $idx => $r) {
            $dayDD = Carbon::parse($r->date)->format('d');
            $patientName = trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? ''));
            $kilometers = $kilometersPerRow[$idx] ?? 0.0;

            $fields = [
                $i,
                $dayDD,
                $r->personal_number ?? '',
                $patientName,
                $r->diagnosis_code ?? '',
                '',
                '',
                'ADOS',
                round($kilometers, 0),
                substr($r->branch_city ?? '', 0, 50),
                substr($r->branch_address ?? '', 0, 50),
                substr($r->patient_city ?? '', 0, 50),
                substr($r->patient_address ?? '', 0, 50),
                $i,
                $userCar,
                '0',
                '',
                'N',
                $r->doctor_pzs ?? '',
                $r->doctor_zpr ?? '',
                'SK',
                '',
                $r->sex ?? '',
                '',
            ];

            $dataLines[] = implode('|', $fields);
            $i++;
        }

        $content = implode("\r\n", array_merge([$line1, $line2], $dataLines)) . "\r\n";
        $fileName = "davka.{$batchNumber}.txt";

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $fileName, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function statementPdf(Request $request)
    {
        $data = $request->validate([
            'batchNumber' => 'required|integer',
            'batchType.code' => 'required|string|in:N,O',
            'insurance.id' => 'required|integer',
            'period' => 'required|array|size:2',
            'period.*' => 'required|date',
            'user.id' => 'required|integer',
            'branch.id' => 'required|integer',
            'company.id' => 'required|integer',
            'patients' => 'array',
            'patients.*.id' => 'integer',
        ]);

        $from = Carbon::parse($data['period'][0])->setTimezone('Europe/Bratislava')->toDateString();
        $to = Carbon::parse($data['period'][1])->setTimezone('Europe/Bratislava')->toDateString();

        $userId = (int) $data['user']['id'];
        $branchId = (int) $data['branch']['id'];
        $companyId = (int) $data['company']['id'];
        $insuranceId = (int) $data['insurance']['id'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')->filter()->values()->all();

        $amount = 0.0;
        $totalKilometers = 0.0;

        $rows = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('branches as b', 'b.id', '=', 'pp.branch_id')
            ->join('procedures as proc', function ($join) {
                $join->where('proc.code', '0000');
            })
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'proc.id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->whereIn('pp.procedure_code', ['3439', '3440'])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->select([
                'pp.id',
                'pp.date',
                'b.latitude as branch_lat',
                'b.longitude as branch_lng',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
                'pcp.price'
            ])
            ->orderBy('pp.date')
            ->get();

        $visitedAddressesPerDay = [];

        foreach ($rows as $row) {
            if (!$this->hasValidCoords($row->branch_lat, $row->branch_lng, $row->patient_lat, $row->patient_lng)) {
                continue;
            }

            $dateString = is_string($row->date) ? $row->date : (method_exists($row->date, 'toDateString') ? $row->date->toDateString() : 'unknown');
            $patientAddressKey = "{$row->patient_lat},{$row->patient_lng}";

            $visitedAddressesPerDay[$dateString] ??= [];

            if (in_array($patientAddressKey, $visitedAddressesPerDay[$dateString], true)) {
                $distanceKm = 0.0;
            } else {
                $distanceKm = $this->getDistanceFromRouteService(
                    $row->branch_lat,
                    $row->branch_lng,
                    $row->patient_lat,
                    $row->patient_lng
                );
                $visitedAddressesPerDay[$dateString][] = $patientAddressKey;
            }

            $totalKilometers += $distanceKm;
            $amount += $distanceKm * (float) $row->price;
        }

        $companyName = DB::table('company')->where('id', $companyId)->value('name');

        $branchName = DB::table('branches')
            ->where('id', $branchId)
            ->selectRaw("TRIM(CONCAT(COALESCE(city,''), ', ', COALESCE(address,''))) as name")
            ->value('name');

        $performedBy = DB::table('users')
            ->where('id', $userId)
            ->selectRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")
            ->value('name');

        $insuranceName = DB::table('insurance_companies')
            ->where('id', $insuranceId)
            ->value('name');

        $sheet = [
            'batchNumber' => $data['batchNumber'],
            'fileName' => "davka.{$data['batchNumber']}.txt",
            'amount' => (string) $amount,
            'kilometers' => round($totalKilometers, 2),
            'periodFrom' => $from,
            'periodTo' => $to,
            'performedBy' => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->setTimezone('Europe/Bratislava')->toDateString(),
            'companyName' => $companyName,
            'branchName' => $branchName,
            'insuranceName' => $insuranceName,
            'fileType' => 'vykázané kilometre',
        ];

        $pdf = Pdf::loadView('pdf.statement', ['sheet' => $sheet])->setPaper('a4');
        $pdfName = "sprievodny_list_{$sheet['batchNumber']}.pdf";

        return $pdf->download($pdfName);
    }

    private function getDistanceFromRouteService($startLat, $startLng, $endLat, $endLng): float
    {
        try {
            $baseUrl = rtrim(config('services.route_service.base_url'), '/');
            $endpoint = ltrim(config('services.route_service.endpoint', '/tsp-solver'), '/');
            $timeout = (int) config('services.route_service.timeout', 8);

            $url = "{$baseUrl}/{$endpoint}";

            $payload = [
                'start_location' => [(float) $startLng, (float) $startLat], // [lng, lat]
                'end_location' => [(float) $endLng, (float) $endLat],
                'points_locations' => [
                ],
            ];

            Log::info('Route service request', ['url' => $url, 'payload' => $payload]);

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withBody(json_encode($payload), 'application/json')
                ->send('GET', $url);

            Log::info('Route service response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::warning('Route service failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return 0.0;
            }

            $json = $response->json();
            $lengthMeters = data_get($json, 'response.0.length');

            if (!is_numeric($lengthMeters)) {
                Log::warning('Route service: missing/invalid length', [
                    'parsed_length' => $lengthMeters,
                    'json' => $json,
                ]);
                return 0.0;
            }

            return ((float) $lengthMeters) / 1000.0;
        } catch (\Throwable $e) {
            Log::error('Route service error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 0.0;
        }
    }

    private function hasValidCoords($branchLat, $branchLng, $patientLat, $patientLng): bool
    {
        return $branchLat !== null && $branchLng !== null && $patientLat !== null && $patientLng !== null;
    }
}
