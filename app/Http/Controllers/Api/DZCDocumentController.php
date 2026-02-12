<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Document;
use App\Models\Patient;
use App\Services\DZCDocumentService;
use App\Http\Requests\StoreDZCRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DZCDocumentController extends Controller
{
    public function __construct(private DZCDocumentService $service)
    {
    }

    public function index(Request $request)
    {
        $query = Document::where('type', 'dzc')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        $documents = $query->get()->map(fn($doc) => [
            'id' => $doc->id,
            'name' => $doc->name,
            'type' => $doc->type,
            'mime_type' => $doc->mime_type,
            'created_at' => $doc->created_at,
            'path' => $doc->path,
        ]);

        return $this->success(['data' => $documents]);
    }

    public function store(StoreDZCRequest $request)
    {
        [$document, $payload] = $this->service->createDzc($request->validated(), $request->user());

        return $this->success(['document_id' => $document->id, 'dzc' => $payload], 'Denný záznam ciest bol úspešne vytvorený', 201);
    }

    public function show(Document $document)
    {
        $document->loadMissing('user');

        $payload = $this->service->getDzcPayload($document);
        if (! $payload) {
            return $this->error('Denný záznam ciest data not found', 404);
        }

        return $this->success(['document' => $document, 'dzc_data' => $payload]);
    }

    public function exportCsv(Document $document)
    {
        $payload = $this->service->getDzcPayload($document);
        if (! $payload) {
            return $this->error('Denný záznam ciest data not found', 404);
        }

        $csv = $this->generateDZCCsv($payload);
        $filename = 'dzc' . ($payload['month'] ?? '') . '_' . ($payload['year'] ?? '') . '.csv';

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
