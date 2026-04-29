<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecordDocumentRequest;
use App\Models\Document;
use App\Models\Patient;
use App\Services\RecordDocumentService;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
        if (!$recordFile) {
            return response()->json(['message' => 'Record data not found'], 404);
        }

        return response()->json(['document' => $document, 'record_data' => $recordFile]);
    }

    public function latestByPatient(Patient $patient, RecordDocumentService $service)
    {
        $document = Document::where('patient_id', $patient->id)
            ->where('type', 'record')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$document) {
            return response()->json(['message' => 'No record found'], 404);
        }

        $recordFile = $service->findRecordFileForDocument($document);
        if (!$recordFile) {
            return response()->json(['message' => 'Record data not found'], 404);
        }

        return response()->json(['document_id' => $document->id, 'record_data' => $recordFile]);
    }

    /**
     * Preview record document as HTML via Blade template.
     */
    public function preview(Document $document, RecordDocumentService $service)
    {
        $document->loadMissing('user');

        $recordData = $service->findRecordFileForDocument($document);
        if (!$recordData) {
            return response()->json(['message' => 'Record data not found'], 404);
        }

        $signatureDataUri = app(DocumentService::class)->getUserSignatureDataUri((int) ($recordData['user_id'] ?? 0));

        return response()->view('pdf.record', [
            'recordData' => $recordData,
            'signatureDataUri' => $signatureDataUri,
        ])->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Download record PDF generated from Blade template.
     */
    public function download(Document $document)
    {
        $document->loadMissing('user');

        $pdfPath = app(DocumentService::class)->getTravelDocumentPdfPath($document);
        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            return response()->json(['message' => 'Record PDF not found'], 500);
        }

        $downloadName = pathinfo($document->name, PATHINFO_FILENAME) . '.pdf';

        return response()->download(Storage::disk('local')->path($pdfPath), $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get a signed public preview URL for record document.
     */
    public function previewUrl(Document $document)
    {
        $this->authorize('view', $document);

        $url = URL::temporarySignedRoute(
            'documents.public',
            now()->addMinutes(15),
            ['document' => $document->id, 'format' => 'html']
        );

        return response()->json(['preview_url' => $url]);
    }
}
