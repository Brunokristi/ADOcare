<?php

namespace App\Http\Controllers\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;


class KilometersExportController extends Controller
{
    use ApiResponse;

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

        $from = Carbon::parse($data['period'][0])
            ->setTimezone('Europe/Bratislava')
            ->toDateString();

        $to = Carbon::parse($data['period'][1])
            ->setTimezone('Europe/Bratislava')
            ->toDateString();
        $userId      = (int) $data['user']['id'];
        $branchId    = (int) $data['branch']['id'];
        $companyId   = (int) $data['company']['id'];
        $insuranceId = (int) $data['insurance']['id'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $amount = 0;
        $totalKilometers = 0;

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
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->select([
                'pp.id',
                'pp.date',
                'b.latitude as branch_lat',
                'b.longitude as branch_lng',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
                'pcp.price'
            ])
            ->get();
        
        $rows = $rows->sortBy('date');
        $visitedAddressesPerDay = [];

        foreach ($rows as $row) {
            if ($row->branch_lat && $row->branch_lng && $row->patient_lat && $row->patient_lng) {
                $dateString = is_string($row->date) ? $row->date : (isset($row->date) ? $row->date->toDateString() : 'unknown');
                $patientAddress = "{$row->patient_lat},{$row->patient_lng}";
                
                if (!isset($visitedAddressesPerDay[$dateString])) {
                    $visitedAddressesPerDay[$dateString] = [];
                }
                
                if (in_array($patientAddress, $visitedAddressesPerDay[$dateString])) {
                    $distance = 0;
                } else {
                    $distance = $this->getDistanceFromOpenRoute(
                        $row->branch_lat,
                        $row->branch_lng,
                        $row->patient_lat,
                        $row->patient_lng
                    );
                    $visitedAddressesPerDay[$dateString][] = $patientAddress;
                }
                
                $totalKilometers += $distance;
                $amount += $distance * $row->price;
            } else {
                
            }
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
            'batchNumber'   => $data['batchNumber'],
            'fileName'      => "davka.{$data['batchNumber']}.txt",
            'amount'        => (string) $amount,
            'kilometers'    => round($totalKilometers, 2),
            'periodFrom'    => $from,
            'periodTo'      => $to,
            'performedBy'   => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->toDateString(),
            'companyName'   => $companyName,
            'branchName'    => $branchName,
            'patients'      => $patientIds,
            'insuranceName'=> $insuranceName,
        ];

        return $this->success(['sheet' => $sheet], 'Preview generated');
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
        $to   = Carbon::parse($data['period'][1])->setTimezone('Europe/Bratislava')->toDateString();

        $type        = $data['batchType']['code']; // N or O
        $batchNumber = (int) $data['batchNumber'];
        $userId      = (int) $data['user']['id'];
        $branchId    = (int) $data['branch']['id'];
        $companyId   = (int) $data['company']['id'];
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
            ->join('patient_branch_users as pbu', function ($join) {
                $join->on('pbu.patient_id', '=', 'p.id')
                    ->on('pbu.user_id', '=', 'pp.user_id')
                    ->on('pbu.branch_id', '=', 'pp.branch_id');
            })
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
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
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

        $rows = $rows->sortBy('date');
        $visitedAddressesPerDay = [];
        $kilometersPerRow = [];
        $rowIndex = 0;

        foreach ($rows as $row) {
            $dateString = is_string($row->date) ? $row->date : (isset($row->date) ? $row->date->toDateString() : 'unknown');
            $patientAddress = "{$row->patient_lat},{$row->patient_lng}";
            
            if (!isset($visitedAddressesPerDay[$dateString])) {
                $visitedAddressesPerDay[$dateString] = [];
            }
            
            if (!in_array($patientAddress, $visitedAddressesPerDay[$dateString])) {
                $distance = $this->getDistanceFromOpenRoute(
                    $row->branch_lat,
                    $row->branch_lng,
                    $row->patient_lat,
                    $row->patient_lng
                );
                $visitedAddressesPerDay[$dateString][] = $patientAddress;
                $kilometersPerRow[$rowIndex] = $distance;
            } else {
                $kilometersPerRow[$rowIndex] = 0;
            }
            $rowIndex++;
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
            number_format((float)$workingTime, 2, '.', ''),
            $termYYYYMM,
            $batchNumber,
            'EUR',
            ''
        ]);

        $dataLines = [];
        $i = 1;
        $rowIndex = 0;
        foreach ($rows as $r) {
            $dayDD   = Carbon::parse($r->date)->format('d');
            $dateYmd = Carbon::parse($r->date)->format('Ymd');
            $patientName = trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? ''));
            $kilometers = $kilometersPerRow[$rowIndex] ?? 0;

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
            $rowIndex++;
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
        $to   = Carbon::parse($data['period'][1])->setTimezone('Europe/Bratislava')->toDateString();

        $userId      = (int) $data['user']['id'];
        $branchId    = (int) $data['branch']['id'];
        $companyId   = (int) $data['company']['id'];
        $insuranceId = (int) $data['insurance']['id'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')->filter()->values()->all();

        $amount = 0;
        $totalKilometers = 0;
        $totalKilometers = 0;

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
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->select([
                'pp.id',
                'pp.date',
                'b.latitude as branch_lat',
                'b.longitude as branch_lng',
                'p.latitude as patient_lat',
                'p.longitude as patient_lng',
                'pcp.price'
            ])
            ->get();

        $rows = $rows->sortBy('date');
        
        $visitedAddressesPerDay = [];

        foreach ($rows as $row) {
            if ($row->branch_lat && $row->branch_lng && $row->patient_lat && $row->patient_lng) {
                $dateString = is_string($row->date) ? $row->date : (isset($row->date) ? $row->date->toDateString() : 'unknown');
                $patientAddress = "{$row->patient_lat},{$row->patient_lng}";
                
                if (!isset($visitedAddressesPerDay[$dateString])) {
                    $visitedAddressesPerDay[$dateString] = [];
                }
                
                if (in_array($patientAddress, $visitedAddressesPerDay[$dateString])) {
                    $distance = 0;
                } else {
                    $distance = $this->getDistanceFromOpenRoute(
                        $row->branch_lat,
                        $row->branch_lng,
                        $row->patient_lat,
                        $row->patient_lng
                    );
                    $visitedAddressesPerDay[$dateString][] = $patientAddress;
                }
                
                $totalKilometers += $distance;
                $amount += $distance * $row->price;
            }
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

        $induranceName = DB::table('insurance_companies')
            ->where('id', $insuranceId)
            ->value('name');

        $sheet = [
            'batchNumber'   => $data['batchNumber'],
            'fileName'      => "davka.{$data['batchNumber']}.txt",
            'amount'        => (string) $amount,
            'kilometers'    => round($totalKilometers, 2),
            'periodFrom'    => $from,
            'periodTo'      => $to,
            'performedBy'   => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->setTimezone('Europe/Bratislava')->toDateString(),
            'companyName'   => $companyName,
            'branchName'    => $branchName,
            'insuranceName'=> $induranceName,
            'fileType'     =>  "vykázané kilometre",
        ];

        $pdf = Pdf::loadView('pdf.statement', ['sheet' => $sheet])
            ->setPaper('a4');

        $pdfName = "sprievodny_list_{$sheet['batchNumber']}.pdf";

        return $pdf->download($pdfName);
    }

    private function getDistanceFromOpenRoute($startLat, $startLng, $endLat, $endLng)
    {
        try {
            $apiKey = config('services.ors.key');
            
            if (!$apiKey) {
                logger()->warning('OpenRoute API key not configured');
                return 0;
            }

            logger()->info('OpenRoute API Call', [
                'start' => "{$startLng},{$startLat}",
                'end' => "{$endLng},{$endLat}",
                'hasApiKey' => !empty($apiKey),
            ]);

            $response = Http::get('https://api.openrouteservice.org/v2/directions/driving-car', [
                'api_key' => $apiKey,
                'start' => "{$startLng},{$startLat}",
                'end' => "{$endLng},{$endLat}",
            ]);

            logger()->info('OpenRoute API Response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $distance = $data['features'][0]['properties']['summary']['distance'] / 1000;
                logger()->info('Distance extracted', ['distance' => $distance]);
                return $distance;
            } else {
                logger()->warning('OpenRoute API failed', ['status' => $response->status()]);
            }
        } catch (\Exception $e) {
            logger()->error('OpenRoute API error', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return 0;
    }

}
