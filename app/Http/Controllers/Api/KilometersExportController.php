<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KilometersExportController extends Controller
{
    private const GROUPING_BUFFER_METERS = 50.0;

    public function preview(Request $request)
    {
        $data = $request->validate([
            'batchNumber' => 'required|string',
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
            ->where('pcp.company_id', $companyId)
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->whereIn('pp.procedure_code', ['3439', '3440'])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->select([
                'pp.id',
                'pp.patient_id',
                'pp.date',
                'p.first_name',
                'p.last_name',
                'b.latitude as branch_lat',
                'b.longitude as branch_lng',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
                'pcp.price',
            ])
            ->orderBy('pp.date')
            ->orderBy('pp.patient_id')
            ->orderBy('pp.id')
            ->get();
                        
        $this->validateKilometersRowsForPreview($rows);


        if ($rows->isEmpty()) {
            Log::warning('Kilometers preview: no rows matched query');
        }

        $visitedAddressesPerDay = [];
        $skippedMissingCoords = 0;
        $calledRouteService = 0;

        foreach ($rows as $row) {
            if (
                $row->branch_lat === null || $row->branch_lng === null ||
                $row->patient_lat === null || $row->patient_lng === null
            ) {
                $skippedMissingCoords++;

                Log::warning('Skipping row due to missing coords', [
                    'pp_id' => $row->id,
                    'patient_id' => $row->patient_id,
                    'branch_lat' => $row->branch_lat,
                    'branch_lng' => $row->branch_lng,
                    'patient_lat' => $row->patient_lat,
                    'patient_lng' => $row->patient_lng,
                ]);

                continue;
            }

            $dateString = $this->normalizeDateString($row->date);
            $visitedAddressesPerDay[$dateString] ??= [];

            if ($this->hasNearbyVisitedAddress(
                $visitedAddressesPerDay[$dateString],
                (float) $row->patient_lat,
                (float) $row->patient_lng,
                self::GROUPING_BUFFER_METERS
            )) {
                $distanceKm = 0.0;
            } else {
                $calledRouteService++;

                $distanceKm = $this->getDistanceFromRouteService(
                    (float) $row->branch_lat,
                    (float) $row->branch_lng,
                    (float) $row->patient_lat,
                    (float) $row->patient_lng
                );

                $visitedAddressesPerDay[$dateString][] = [
                    'lat' => (float) $row->patient_lat,
                    'lng' => (float) $row->patient_lng,
                    'patient_id' => (int) $row->patient_id,
                    'pp_id' => (int) $row->id,
                ];
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
            'patients' => $patientIds,
            'insuranceName' => $insuranceName,
            'debug' => [
                'grouping_buffer_meters' => self::GROUPING_BUFFER_METERS,
                'skipped_missing_coords' => $skippedMissingCoords,
                'route_service_calls' => $calledRouteService,
            ],
        ];

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

        $type = $data['batchType']['code'];
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
            ->whereColumn('p.nurse_id', 'pp.user_id')
            ->whereColumn('p.branch_id', 'pp.branch_id')
            ->join('procedures as proc', function ($join) {
                $join->where('proc.code', '0000');
            })
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'proc.id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pcp.company_id', $companyId)
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->whereIn('pp.procedure_code', ['3439', '3440'])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->orderBy('pp.date')
            ->orderBy('pp.patient_id')
            ->orderBy('pp.id')
            ->select([
                'pp.id',
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
                'pcp.price',
            ])
            ->get();

        $this->validateKilometersDownloadData(
            rows: $rows,
            company: $company,
            branch: $branch,
            user: $user,
            workingTime: $workingTime,
            insuranceBranchCode: $insuranceBranchCode,
            userCar: $userCar,
        );

        $visitedAddressesPerDay = [];
        $kilometersPerRow = [];

        foreach ($rows as $idx => $row) {
            if (!$this->hasValidCoords($row->branch_lat, $row->branch_lng, $row->patient_lat, $row->patient_lng)) {
                $kilometersPerRow[$idx] = 0.0;
                continue;
            }

            $dateString = $this->normalizeDateString($row->date);
            $visitedAddressesPerDay[$dateString] ??= [];

            if ($this->hasNearbyVisitedAddress(
                $visitedAddressesPerDay[$dateString],
                (float) $row->patient_lat,
                (float) $row->patient_lng,
                self::GROUPING_BUFFER_METERS
            )) {
                $kilometersPerRow[$idx] = 0.0;
                continue;
            }

            $km = $this->getDistanceFromRouteService(
                (float) $row->branch_lat,
                (float) $row->branch_lng,
                (float) $row->patient_lat,
                (float) $row->patient_lng
            );

            $visitedAddressesPerDay[$dateString][] = [
                'lat' => (float) $row->patient_lat,
                'lng' => (float) $row->patient_lng,
                'patient_id' => (int) $row->patient_id,
                'pp_id' => (int) $row->id,
            ];

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
            '',
        ]);

        $line2 = implode('|', [
            $branch->identificator ?? '',
            $branch->code ?? '',
            $user->code ?? '',
            number_format((float) $workingTime, 2, '.', ''),
            $termYYYYMM,
            $batchNumber,
            'EUR',
            '',
        ]);

        $dataLines = [];
        $i = 1;

        foreach ($rows as $idx => $r) {
            $dayDD = Carbon::parse($r->date)->format('d');
            $patientName = $this->toAsciiString(trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? '')));
            $kilometers = $kilometersPerRow[$idx] ?? 0.0;

            $fields = [
                $i,
                $dayDD,
                $this->toAsciiString($r->personal_number ?? ''),
                $patientName,
                $this->toAsciiString($r->diagnosis_code ?? ''),
                '',
                '',
                'ADOS',
                round($kilometers, 0),
                $this->toAsciiString($r->branch_city ?? '', 50),
                $this->toAsciiString($r->branch_address ?? '', 50),
                $this->toAsciiString($r->patient_city ?? '', 50),
                $this->toAsciiString($r->patient_address ?? '', 50),
                $i,
                $this->toAsciiString($userCar),
                '0',
                '',
                'N',
                $this->toAsciiString($r->doctor_pzs ?? ''),
                $this->toAsciiString($r->doctor_zpr ?? ''),
                'SK',
                '',
                $this->toAsciiString($r->sex ?? ''),
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
            ->pluck('id')
            ->filter()
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
            ->where('pcp.company_id', $companyId)
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->whereIn('pp.procedure_code', ['3439', '3440'])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->select([
                'pp.id',
                'pp.patient_id',
                'pp.date',
                'p.first_name',
                'p.last_name',
                'b.latitude as branch_lat',
                'b.longitude as branch_lng',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
                'pcp.price',
            ])
            ->orderBy('pp.date')
            ->orderBy('pp.patient_id')
            ->orderBy('pp.id')
            ->get();

        $this->validateKilometersRowsForPreview($rows);

        $visitedAddressesPerDay = [];

        foreach ($rows as $row) {
            if (!$this->hasValidCoords($row->branch_lat, $row->branch_lng, $row->patient_lat, $row->patient_lng)) {
                continue;
            }

            $dateString = $this->normalizeDateString($row->date);
            $visitedAddressesPerDay[$dateString] ??= [];

            if ($this->hasNearbyVisitedAddress(
                $visitedAddressesPerDay[$dateString],
                (float) $row->patient_lat,
                (float) $row->patient_lng,
                self::GROUPING_BUFFER_METERS
            )) {
                $distanceKm = 0.0;
            } else {
                $distanceKm = $this->getDistanceFromRouteService(
                    (float) $row->branch_lat,
                    (float) $row->branch_lng,
                    (float) $row->patient_lat,
                    (float) $row->patient_lng
                );

                $visitedAddressesPerDay[$dateString][] = [
                    'lat' => (float) $row->patient_lat,
                    'lng' => (float) $row->patient_lng,
                    'patient_id' => (int) $row->patient_id,
                    'pp_id' => (int) $row->id,
                ];
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

    private function normalizeDateString($date): string
    {
        return is_string($date)
            ? $date
            : (method_exists($date, 'toDateString') ? $date->toDateString() : 'unknown');
    }

    private function hasNearbyVisitedAddress(array $visitedPoints, float $lat, float $lng, float $bufferMeters = 100.0): bool
    {
        foreach ($visitedPoints as $point) {
            $distance = $this->distanceInMeters(
                (float) $point['lat'],
                (float) $point['lng'],
                $lat,
                $lng
            );

            if ($distance <= $bufferMeters) {
                return true;
            }
        }

        return false;
    }

    private function distanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function getDistanceFromRouteService($startLat, $startLng, $endLat, $endLng): float
    {
        try {
            $baseUrl = rtrim(config('services.route_service.base_url'), '/');
            $endpoint = ltrim(config('services.route_service.endpoint', '/tsp-solver'), '/');
            $timeout = (int) config('services.route_service.timeout', 8);

            $url = "{$baseUrl}/{$endpoint}";

            $payload = [
                'start_location' => [(float) $startLng, (float) $startLat],
                'end_location' => [(float) $endLng, (float) $endLat],
                'points_locations' => [],
            ];

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withBody(json_encode($payload), 'application/json')
                ->send('GET', $url);

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
        return $branchLat !== null
            && $branchLng !== null
            && $patientLat !== null
            && $patientLng !== null;
    }

    private function toAsciiString(?string $value, ?int $limit = null): string
    {
        $normalized = Str::ascii((string) ($value ?? ''));
        $normalized = str_replace(["\r", "\n", '|'], ' ', $normalized);
        $normalized = preg_replace('/[^\x20-\x7E]/', '', $normalized) ?? '';
        $normalized = trim($normalized);

        if ($limit !== null) {
            return substr($normalized, 0, $limit);
        }

        return $normalized;
    }

    private function validateKilometersRowsForPreview($rows): void
    {
        $errors = [];

        if ($rows->isEmpty()) {
            $errors[] = 'Nenašli sa žiadne výkony pre zadané filtre.';
        }

        foreach ($rows as $row) {
            $patientName = $this->formatPatientName($row);

            $this->addMissingError($errors, $row->branch_lat, "Chýba GPS latitude prevádzky pri pacientovi {$patientName}.");
            $this->addMissingError($errors, $row->branch_lng, "Chýba GPS longitude prevádzky pri pacientovi {$patientName}.");
            $this->addMissingError($errors, $row->patient_lat, "Chýba GPS latitude pacienta {$patientName}.");
            $this->addMissingError($errors, $row->patient_lng, "Chýba GPS longitude pacienta {$patientName}.");
            $this->addMissingError($errors, $row->price, "Chýba cena výkonu 0000 pre pacienta {$patientName}.");
        }

        $this->throwKilometersValidationErrors($errors);
    }

    private function validateKilometersDownloadData(
        $rows,
        $company,
        $branch,
        $user,
        $workingTime,
        $insuranceBranchCode,
        $userCar,
    ): void {
        $errors = [];

        if ($rows->isEmpty()) {
            $errors[] = 'Nenašli sa žiadne výkony pre zadané filtre.';
        }

        $this->addMissingError($errors, $company?->ico ?? null, 'Chýba IČO spoločnosti.');
        $this->addMissingError($errors, $company?->name ?? null, 'Chýba názov spoločnosti.');

        $this->addMissingError($errors, $branch?->identificator ?? null, 'Chýba identifikátor prevádzky.');
        $this->addMissingError($errors, $branch?->code ?? null, 'Chýba kód prevádzky.');

        $this->addMissingError($errors, $user?->code ?? null, 'Chýba kód používateľa.');
        $this->addMissingError($errors, $workingTime, 'Chýba pracovný čas používateľa na prevádzke.');
        $this->addMissingError($errors, $insuranceBranchCode, 'Chýba kód pobočky poisťovne.');
        $this->addMissingError($errors, $userCar, 'Chýba EČV vozidla používateľa.');

        foreach ($rows as $row) {
            $patientName = $this->formatPatientName($row);

            $this->addMissingError($errors, $row->personal_number, "Chýba rodné číslo pacienta {$patientName}.");
            $this->addMissingError($errors, $row->last_name, "Chýba priezvisko pacienta {$patientName}.");
            $this->addMissingError($errors, $row->first_name, "Chýba meno pacienta {$patientName}.");
            $this->addMissingError($errors, $row->sex, "Chýba pohlavie pacienta {$patientName}.");

            $this->addMissingError($errors, $row->patient_city, "Chýba mesto pacienta {$patientName}.");
            $this->addMissingError($errors, $row->patient_address, "Chýba adresa pacienta {$patientName}.");

            $this->addMissingError($errors, $row->patient_lat, "Chýba GPS latitude pacienta {$patientName}.");
            $this->addMissingError($errors, $row->patient_lng, "Chýba GPS longitude pacienta {$patientName}.");

            $this->addMissingError($errors, $row->diagnosis_code, "Chýba diagnóza pri pacientovi {$patientName}.");
            $this->addMissingError($errors, $row->procedure_code, "Chýba kód výkonu pri pacientovi {$patientName}.");

            $this->addMissingError($errors, $row->doctor_pzs, "Chýba PZS lekára pri pacientovi {$patientName}.");
            $this->addMissingError($errors, $row->doctor_zpr, "Chýba ZPR lekára pri pacientovi {$patientName}.");

            $this->addMissingError($errors, $row->branch_city, "Chýba mesto prevádzky pri pacientovi {$patientName}.");
            $this->addMissingError($errors, $row->branch_address, "Chýba adresa prevádzky pri pacientovi {$patientName}.");
            $this->addMissingError($errors, $row->branch_lat, "Chýba GPS latitude prevádzky pri pacientovi {$patientName}.");
            $this->addMissingError($errors, $row->branch_lng, "Chýba GPS longitude prevádzky pri pacientovi {$patientName}.");

            $this->addMissingError($errors, $row->price, "Chýba cena výkonu 0000 pre pacienta {$patientName}.");
        }

        $this->throwKilometersValidationErrors($errors);
    }

    private function addMissingError(array &$errors, mixed $value, string $message): void
    {
        if (!$this->isFilledValue($value)) {
            $errors[] = $message;
        }
    }

    private function isFilledValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }

    private function formatPatientName(object $row): string
    {
        $name = trim(($row->last_name ?? '') . ' ' . ($row->first_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        if (!empty($row->patient_id)) {
            return "#{$row->patient_id}";
        }

        return 'neznámy pacient';
    }

    private function throwKilometersValidationErrors(array $errors): void
    {
        $errors = array_values(array_unique($errors));

        if (!$errors) {
            return;
        }

        throw ValidationException::withMessages([
            'kilometers_export' => $errors,
        ]);
    }
}