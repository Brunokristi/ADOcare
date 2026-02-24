<?php

// TODO: Move to Service and Form Request classes

namespace App\Http\Controllers\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;


class PointsExportController extends Controller
{


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

        $from = Carbon::parse($data['period'][0])
            ->setTimezone('Europe/Bratislava')
            ->toDateString();

        $to = Carbon::parse($data['period'][1])
            ->setTimezone('Europe/Bratislava')
            ->toDateString();
        $userId = (int) $data['user']['id'];
        $branchId = (int) $data['branch']['id'];
        $companyId = (int) $data['company']['id'];
        $insuranceId = (int) $data['insurance']['id'];
        $batchTypeCode = $data['batchType']['code'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->values()
            ->all();

        logger()->info('Preview patientIds', [
            'patients_payload' => $data['patients'] ?? null,
            'patientIds' => $patientIds,
        ]);

        // Fetch rows for amount calculation with deduplication
        $pointsData = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->when(in_array($batchTypeCode, ['N', 'O']), fn($q) => $q->where('p.country_id', 207))
            ->when(in_array($batchTypeCode, ['E', 'F']), fn($q) => $q->where('p.country_id', '!=', 207))
            ->select([
                'pp.date',
                'pp.procedure_code',
                'pp.quantity',
                'pcp.price',
                'p.latitude',
                'p.longitude',
            ])
            ->get();

        // Deduplicate same-address visits for procedures 3439 and 3440
        $seenAddresses = [];
        $filteredPointsData = [];

        foreach ($pointsData as $row) {
            $procedureCode = $row->procedure_code ?? '';
            
            if (in_array($procedureCode, ['3439', '3440'])) {
                $addressKey = $row->date . '|' . $row->latitude . '|' . $row->longitude;
                
                if (isset($seenAddresses[$addressKey])) {
                    continue;
                }
                
                $seenAddresses[$addressKey] = true;
            }
            
            $filteredPointsData[] = $row;
        }

        // Calculate amount from filtered data
        $amount = collect($filteredPointsData)
            ->sum(fn($row) => $row->quantity * $row->price);

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
            'periodFrom' => $from,
            'periodTo' => $to,
            'performedBy' => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->toDateString(),
            'companyName' => $companyName,
            'branchName' => $branchName,
            'patients' => $patientIds,
            'insuranceName' => $insuranceName,
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
            'batchType.code' => 'required|string|in:N,O,I,E,F',
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

        logger()->info('Download patientIds', [
            'patients_payload' => $data['patients'] ?? null,
            'patientIds' => $patientIds,
            'batchType' => $type,
        ]);

        // Header data
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

        $termYYYYMM = Carbon::parse($from)->format('Ym');
        $generatedYmd = now()->setTimezone('Europe/Bratislava')->format('Ymd');

        // Rows
        $rows = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('doctors as d', 'd.id', '=', 'p.doctor_id')
            ->join('countries as c', 'c.id', '=', 'p.country_id')
            // replaced pivot join with direct patient fields (nurse_id, branch_id)
            ->whereColumn('p.nurse_id', 'pp.user_id')
            ->whereColumn('p.branch_id', 'pp.branch_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->when(in_array($type, ['N', 'O']), fn($q) => $q->where('p.country_id', 207))
            ->when(in_array($type, ['E', 'F']), fn($q) => $q->where('p.country_id', '!=', 207))
            ->orderBy('pp.date')
            ->select([
                'pp.date',
                'p.personal_number',
                'p.last_name',
                'p.first_name',
                'pp.diagnosis_code',
                'pp.procedure_code',
                'pp.quantity',
                'd.pzs as doctor_pzs',
                'd.zpr as doctor_zpr',
                'p.latitude',
                'p.longitude',
                'c.code as country_code',
                'p.sex',
            ])
            ->get();

        // Deduplicate same-address visits for procedures 3439 and 3440
        $seenAddresses = [];
        $filteredRows = [];

        foreach ($rows as $r) {
            $procedureCode = $r->procedure_code ?? '';
            
            // If procedure is 3439 or 3440, check if we've already seen this address on this date
            if (in_array($procedureCode, ['3439', '3440'])) {
                $addressKey = $r->date . '|' . $r->latitude . '|' . $r->longitude;
                
                if (isset($seenAddresses[$addressKey])) {
                    // Skip this record, we already have one for this address/date
                    continue;
                }
                
                $seenAddresses[$addressKey] = true;
            }
            
            $filteredRows[] = $r;
        }

        $rows = collect($filteredRows);
        $rowCount = $rows->count();

        // Line 1 (with trailing |)
        $line1 = implode('|', [
            $type,
            '753b',
            $company->ico ?? '',
            $generatedYmd,
            $batchNumber,
            $rowCount,
            '1',
            '1',
            $insuranceBranchCode ?? '',
            ''
        ]);

        // Line 2 (with trailing | and the empty field before EUR)
        $line2 = implode('|', [
            $branch->identificator ?? '',
            $branch->code ?? '',
            $user->code ?? '',
            number_format((float) $workingTime, 2, '.', ''),
            $termYYYYMM,
            '850',
            $batchNumber,
            'EUR',
            ''
        ]);

        // Data lines
        $dataLines = [];
        $i = 1;

        foreach ($rows as $r) {
            $dayDD = Carbon::parse($r->date)->format('d');
            $dateYmd = Carbon::parse($r->date)->format('Ymd');

            $patientName = trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? ''));

            $fields = [
                $i,
                $dayDD,
                in_array($type, ['E', 'F']) ? '' : ($r->personal_number ?? ''),
                $patientName,
                $r->diagnosis_code ?? '',
                $r->procedure_code ?? '',
                $r->quantity ?? 1,
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'O',
                $r->doctor_pzs ?? '',
                $r->doctor_zpr ?? '',
                in_array($type, ['E', 'F']) ? ($r->country_code ?? '') : '',
                in_array($type, ['E', 'F']) ? ($r->personal_number ?? '') : '',
                in_array($type, ['E', 'F']) ? ($r->sex ?? '') : '',
                $dateYmd,
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
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
            'batchType.code' => 'required|string|in:N,O,I,E,F',
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
        $pdfBatchType = $data['batchType']['code'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')->filter()->values()->all();

        // Fetch rows for amount calculation with deduplication
        $pointsData = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->when(in_array($pdfBatchType, ['N', 'O']), fn($q) => $q->where('p.country_id', 207))
            ->when(in_array($pdfBatchType, ['E', 'F']), fn($q) => $q->where('p.country_id', '!=', 207))
            ->select([
                'pp.date',
                'pp.procedure_code',
                'pp.quantity',
                'pcp.price',
                'p.latitude',
                'p.longitude',
            ])
            ->get();

        // Deduplicate same-address visits for procedures 3439 and 3440
        $seenAddresses = [];
        $filteredPointsData = [];

        foreach ($pointsData as $row) {
            $procedureCode = $row->procedure_code ?? '';
            
            if (in_array($procedureCode, ['3439', '3440'])) {
                $addressKey = $row->date . '|' . $row->latitude . '|' . $row->longitude;
                
                if (isset($seenAddresses[$addressKey])) {
                    continue;
                }
                
                $seenAddresses[$addressKey] = true;
            }
            
            $filteredPointsData[] = $row;
        }

        // Calculate amount from filtered data
        $amount = collect($filteredPointsData)
            ->sum(fn($row) => $row->quantity * $row->price);

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
            'periodFrom' => $from,
            'periodTo' => $to,
            'performedBy' => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->setTimezone('Europe/Bratislava')->toDateString(),
            'companyName' => $companyName,
            'branchName' => $branchName,
            'insuranceName' => $insuranceName,
            'fileType' => "vykázané body",
        ];

        $pdf = Pdf::loadView('pdf.statement', ['sheet' => $sheet])
            ->setPaper('a4');

        $pdfName = "sprievodny_list_{$sheet['batchNumber']}.pdf";

        return $pdf->download($pdfName);
    }

}
