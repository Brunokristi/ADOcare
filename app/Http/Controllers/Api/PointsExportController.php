<?php

// TODO: Move to Service and Form Request classes

namespace App\Http\Controllers\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

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
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        logger()->info('Preview patientIds', [
            'patients_payload' => $data['patients'] ?? null,
            'patientIds' => $patientIds,
        ]);

        $pointsData = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pcp.company_id', $companyId)
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->when(in_array($batchTypeCode, ['N', 'O'], true), fn ($q) => $q->where('p.country_id', 207))
            ->when(in_array($batchTypeCode, ['E', 'F'], true), fn ($q) => $q->where('p.country_id', '!=', 207))
            ->select([
                'pp.date',
                'pp.procedure_code',
                'pp.quantity',
                'pcp.price',
                'p.latitude',
                'p.longitude',
            ])
            ->orderBy('pp.date')
            ->get();

        $filteredPointsData = $this->deduplicateNearbyProcedureRows($pointsData, 50);

        $amount = collect($filteredPointsData)
            ->sum(fn ($row) => $row->quantity * $row->price);

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

        $from = Carbon::parse($data['period'][0])
            ->setTimezone('Europe/Bratislava')
            ->toDateString();

        $to = Carbon::parse($data['period'][1])
            ->setTimezone('Europe/Bratislava')
            ->toDateString();

        $type = $data['batchType']['code'];
        $batchNumber = (int) $data['batchNumber'];
        $userId = (int) $data['user']['id'];
        $branchId = (int) $data['branch']['id'];
        $companyId = (int) $data['company']['id'];
        $insuranceId = (int) $data['insurance']['id'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        logger()->info('Download patientIds', [
            'patients_payload' => $data['patients'] ?? null,
            'patientIds' => $patientIds,
            'batchType' => $type,
        ]);

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

        $rows = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('doctors as d', 'd.id', '=', 'p.doctor_id')
            ->join('countries as c', 'c.id', '=', 'p.country_id')
            ->whereColumn('p.nurse_id', 'pp.user_id')
            ->whereColumn('p.branch_id', 'pp.branch_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pcp.company_id', $companyId)
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->when(in_array($type, ['N', 'O'], true), fn ($q) => $q->where('p.country_id', 207))
            ->when(in_array($type, ['E', 'F'], true), fn ($q) => $q->where('p.country_id', '!=', 207))
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

        $rows = collect($this->deduplicateNearbyProcedureRows($rows, 50));
        $rowCount = $rows->count();

        $line1 = implode('|', [
            $this->toAsciiString($type),
            '753b',
            $this->toAsciiString($company->ico ?? ''),
            $generatedYmd,
            $batchNumber,
            $rowCount,
            '1',
            '1',
            $this->toAsciiString($insuranceBranchCode ?? ''),
            '',
        ]);

        $line2 = implode('|', [
            $this->toAsciiString($branch->identificator ?? ''),
            $this->toAsciiString($branch->code ?? ''),
            $this->toAsciiString($user->code ?? ''),
            number_format((float) $workingTime, 2, '.', ''),
            $termYYYYMM,
            '850',
            $batchNumber,
            'EUR',
            '',
        ]);

        $dataLines = [];
        $i = 1;

        foreach ($rows as $r) {
            $dayDD = Carbon::parse($r->date)->format('d');
            $dateYmd = Carbon::parse($r->date)->format('Ymd');

            $patientName = $this->toAsciiString(
                trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? ''))
            );

            $fields = [
                $i,
                $dayDD,
                in_array($type, ['E', 'F'], true) ? '' : $this->toAsciiString($r->personal_number ?? ''),
                $patientName,
                $this->toAsciiString($r->diagnosis_code ?? ''),
                $this->toAsciiString($r->procedure_code ?? ''),
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
                $this->toAsciiString($r->doctor_pzs ?? ''),
                $this->toAsciiString($r->doctor_zpr ?? ''),
                in_array($type, ['E', 'F'], true) ? $this->toAsciiString($r->country_code ?? '') : '',
                in_array($type, ['E', 'F'], true) ? $this->toAsciiString($r->personal_number ?? '') : '',
                in_array($type, ['E', 'F'], true) ? $this->toAsciiString($r->sex ?? '') : '',
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
        $pdfBatchType = $data['batchType']['code'];

        $patientIds = collect($data['patients'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $pointsData = DB::table('patient_points as pp')
            ->join('patients as p', 'p.id', '=', 'pp.patient_id')
            ->join('procedure_company_prices as pcp', function ($join) {
                $join->on('pcp.procedure_id', '=', 'pp.procedure_id')
                    ->on('pcp.insurance_company_id', '=', 'p.insurance_company_id');
            })
            ->where('pcp.company_id', $companyId)
            ->where('pp.user_id', $userId)
            ->where('pp.branch_id', $branchId)
            ->where('p.insurance_company_id', $insuranceId)
            ->whereBetween('pp.date', [$from, $to])
            ->when(!empty($patientIds), fn ($q) => $q->whereIn('pp.patient_id', $patientIds))
            ->when(in_array($pdfBatchType, ['N', 'O'], true), fn ($q) => $q->where('p.country_id', 207))
            ->when(in_array($pdfBatchType, ['E', 'F'], true), fn ($q) => $q->where('p.country_id', '!=', 207))
            ->select([
                'pp.date',
                'pp.procedure_code',
                'pp.quantity',
                'pcp.price',
                'p.latitude',
                'p.longitude',
            ])
            ->orderBy('pp.date')
            ->get();

        $filteredPointsData = $this->deduplicateNearbyProcedureRows($pointsData, 50);

        $amount = collect($filteredPointsData)
            ->sum(fn ($row) => $row->quantity * $row->price);

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
            'fileType' => 'vykázané body',
        ];

        $pdf = Pdf::loadView('pdf.statement', ['sheet' => $sheet])
            ->setPaper('a4');

        $pdfName = "sprievodny_list_{$sheet['batchNumber']}.pdf";

        return $pdf->download($pdfName);
    }

    private function deduplicateNearbyProcedureRows(iterable $rows, int $radiusMeters = 50): array
    {
        $seenAddresses = [];
        $filteredRows = [];

        foreach ($rows as $row) {
            $procedureCode = (string) ($row->procedure_code ?? '');

            $shouldDeduplicate =
                in_array($procedureCode, ['3439', '3440'], true) &&
                $row->latitude !== null &&
                $row->longitude !== null;

            if (! $shouldDeduplicate) {
                $filteredRows[] = $row;
                continue;
            }

            $bucketKey = (string) $row->date . '|' . $procedureCode;

            if (!isset($seenAddresses[$bucketKey])) {
                $seenAddresses[$bucketKey] = [];
            }

            $currentLatitude = (float) $row->latitude;
            $currentLongitude = (float) $row->longitude;

            $isNearby = false;

            foreach ($seenAddresses[$bucketKey] as $seenPoint) {
                $distanceMeters = $this->distanceInMeters(
                    $currentLatitude,
                    $currentLongitude,
                    $seenPoint['latitude'],
                    $seenPoint['longitude']
                );

                if ($distanceMeters <= $radiusMeters) {
                    $isNearby = true;
                    break;
                }
            }

            if ($isNearby) {
                continue;
            }

            $seenAddresses[$bucketKey][] = [
                'latitude' => $currentLatitude,
                'longitude' => $currentLongitude,
            ];

            $filteredRows[] = $row;
        }

        return $filteredRows;
    }

    private function distanceInMeters(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $latFrom = deg2rad($latitude1);
        $lonFrom = deg2rad($longitude1);
        $latTo = deg2rad($latitude2);
        $lonTo = deg2rad($longitude2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $earthRadius * $angle;
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
}