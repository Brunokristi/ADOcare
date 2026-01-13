<?php

// TODO: Move to Service and Form Request classes

namespace App\Http\Controllers\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;


class PointsExportController extends Controller
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

        logger()->info('Preview patientIds', [
            'patients_payload' => $data['patients'] ?? null,
            'patientIds' => $patientIds,
        ]);

        $amount = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->selectRaw('COALESCE(SUM(pp.quantity * pcp.price), 0) as total')
            ->value('total');

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
            'periodFrom'    => $from,
            'periodTo'      => $to,
            'performedBy'   => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->toDateString(),
            'companyName'   => $companyName,
            'branchName'    => $branchName,
            'patients'      => $patientIds,
            'insuranceName'=> $insuranceName,
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

        logger()->info('Download patientIds', [
            'patients_payload' => $data['patients'] ?? null,
            'patientIds' => $patientIds,
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
            ->join('patient_branch_users as pbu', function ($join) {
                $join->on('pbu.patient_id', '=', 'p.id')
                    ->on('pbu.user_id', '=', 'pp.user_id')
                    ->on('pbu.branch_id', '=', 'pp.branch_id');
            })
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
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
            ])
            ->get();

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
            number_format((float)$workingTime, 2, '.', ''),
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
            $dayDD   = Carbon::parse($r->date)->format('d');
            $dateYmd = Carbon::parse($r->date)->format('Ymd');

            $patientName = trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? ''));

            $fields = [
                $i,
                $dayDD,
                $r->personal_number ?? '',
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
                '',
                '',
                '',
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

        $amount = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->selectRaw('COALESCE(SUM(pp.quantity * pcp.price), 0) as total')
            ->value('total');

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
            'periodFrom'    => $from,
            'periodTo'      => $to,
            'performedBy'   => $performedBy ?: "User #{$userId}",
            'performedDate' => now()->setTimezone('Europe/Bratislava')->toDateString(),
            'companyName'   => $companyName,
            'branchName'    => $branchName,
            'insuranceName'=> $insuranceName,
            'fileType'     =>  "vykázané body",
        ];

        $pdf = Pdf::loadView('pdf.statement', ['sheet' => $sheet])
            ->setPaper('a4');

        $pdfName = "sprievodny_list_{$sheet['batchNumber']}.pdf";

        return $pdf->download($pdfName);
    }

}
