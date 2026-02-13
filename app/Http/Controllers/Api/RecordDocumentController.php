<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecordDocumentRequest;
use App\Models\Document;
use App\Services\RecordDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecordDocumentController extends Controller
{
    public function store(StoreRecordDocumentRequest $request, RecordDocumentService $service)
    {
        $document = $service->create($request->validated(), Auth::user());

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Ošetrovateľský záznam bol úspešne vytvorený',
        ], 201);
    }

    public function show(Document $document, RecordDocumentService $service)
    {
        $document->loadMissing('patient');

        $recordFile = $service->findRecordFileForDocument($document);
        if (! $recordFile) {
            return response()->json(['message' => 'Record data not found'], 404);
        }

        return response()->json(['document' => $document, 'record_data' => $recordFile]);
    }

    public function latestByPatient($patientId, RecordDocumentService $service)
    {
        $patientId = (int) $patientId;

        $document = Document::where('patient_id', $patientId)
            ->where('type', 'record')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (! $document) {
            return response()->json(['message' => 'No record found'], 404);
        }

        $recordFile = $service->findRecordFileForDocument($document);
        if (! $recordFile) {
            return response()->json(['message' => 'Record data not found'], 404);
        }

        return response()->json(['document_id' => $document->id, 'record_data' => $recordFile]);
    }
}
