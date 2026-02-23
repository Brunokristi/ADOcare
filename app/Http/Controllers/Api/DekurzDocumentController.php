<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\PatientPoint;
use App\Http\Requests\StoreDekurzRequest;
use App\Services\DekurzDocumentService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;


class DekurzDocumentController extends Controller
{
    public function store(StoreDekurzRequest $request, DekurzDocumentService $service)
    {
        $document = $service->create($request->validated(), Auth::user());

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Dekurz bol úspešne vytvorený',
        ], 201);
    }

    public function show(Document $document, DekurzDocumentService $service)
    {
        $document->loadMissing(['user', 'patient']);

        $dekurzFile = $service->findDekurzFileForDocument($document);
        if (! $dekurzFile) {
            return response()->json(['message' => 'Dekurz data not found'], 404);
        }

        return response()->json(['document' => $document, 'dekurz_data' => $dekurzFile]);
    }

    public function last(Request $request, DekurzDocumentService $service)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
        ]);

        $doc = Document::query()
            ->where('type', 'dekurz')
            ->where('patient_id', (int) $data['patient_id'])
            ->orderByDesc('id')
            ->first();

        if (! $doc) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $dekurz = $service->findDekurzFileForDocument($doc);
        if (! $dekurz) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json(['success' => true, 'data' => ['document_id' => $doc->id, 'sections' => $dekurz['sections'] ?? []]]);
    }

    public function availableDates(Request $request, DekurzDocumentService $service)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'month' => 'required|date',
        ]);

        $result = $service->getAvailableDates((int) $data['patient_id'], $data['month']);

        return response()->json(['success' => true, 'message' => 'Available dates retrieved', 'data' => $result]);
    }

}
