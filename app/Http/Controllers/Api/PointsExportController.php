<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;

class PointsExportController extends Controller
{
    public function preview(Request $request)
    {
        $data = $request->validate([
            'batchNumber'           => 'required|integer',
            'batchType.code'        => 'required|string',
            'insurance.id'          => 'required|integer',
            'period'                => 'required|array|size:2',
            'period.*'              => 'required|date',
            'patients'              => 'array',
            'patients.*.id'         => 'integer',
        ]);

        // ✅ dummy data, no DB queries
        $sheet = [
            'batchNumber'   => $data['batchNumber'],
            'fileName'      => "davka.{$data['batchNumber']}.txt",
            'amount'        => 123.45,
            'periodFrom'    => $data['period'][0],
            'periodTo'      => $data['period'][1],
            'performedBy'   => 'Test User',
            'performedDate' => now()->toDateString(),
            'companyName'   => 'ADOS ADANED s.r.o.',
            'branchName'    => 'Lučenec, Mierová 1A',
        ];

        return response()->json([
            'sheet' => $sheet,
        ]);
    }

    public function download(Request $request)
    {
        $data = $request->validate([
            'batchNumber'           => 'required|integer',
            'batchType.code'        => 'required|string',
            'insurance.id'          => 'required|integer',
            'period'                => 'required|array|size:2',
            'period.*'              => 'required|date',
            'patients'              => 'array',
            'patients.*.id'         => 'integer',
        ]);

        $content = $this->generateTxtContent($data);
        $filename = "davka.{$data['batchNumber']}.txt";

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    protected function generateTxtContent(array $data): string
    {
        // dummy TXT content for now
        $lines = [];

        $lines[] = "Davka: {$data['batchNumber']}";
        $lines[] = "Typ: {$data['batchType']['code']}";
        $lines[] = "Poistovna ID: {$data['insurance']['id']}";
        $lines[] = "Obdobie: {$data['period'][0]} - {$data['period'][1]}";
        $lines[] = "Toto je testovaci subor, nahradime ho neskor.";

        return implode("\r\n", $lines) . "\r\n";
    }
}
