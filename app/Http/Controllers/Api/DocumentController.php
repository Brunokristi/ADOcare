<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $service)
    {
    }

    public function createCompanyTravelDocument(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:cp,dzc'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'period' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $result = $this->service->createCompanyTravelDocument($validated, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            [
                'document_id' => $result['document']->id,
                'type' => $result['type'],
            ],
            $result['type'] === 'cp'
            ? 'Cestovný príkaz bol úspešne vytvorený'
            : 'Denný záznam ciest bol úspešne vytvorený',
            201
        );
    }

    public function indexTravelDocuments(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $branchId = $request->input('branch_id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;
        $period = $request->input('period');

        $documents = $this->service->getTravelDocuments(
            Auth::id(),
            $branchId,
            $period,
            $perPage
        );

        return response()->json($documents);
    }

    public function indexTravelDocumentsForCompany(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $branchIds = $request->input('branch_ids');
        $period = $request->input('period');

        $branchIdArray = [];
        if ($branchIds) {
            if (is_string($branchIds)) {
                $branchIdArray = array_map('intval', array_filter(explode(',', $branchIds)));
            } elseif (is_array($branchIds)) {
                $branchIdArray = array_map('intval', $branchIds);
            }
        }

        $documents = $this->service->getTravelDocumentsForCompany($branchIdArray, $period, $perPage);

        return response()->json($documents);
    }

    /**
     * Display a listing of documents for a patient.
     */
    public function index(Request $request, $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $this->authorize('view', $patient);

        $query = Document::where('patient_id', $patientId);

        $results = ApiQuery::apply(
            $request,
            $query,
            ['name', 'type', 'created_at'],
            [],
            ['sort' => '-created_at']
        );

        return response()->json($results);
    }

    /**
     * Store a newly created document.
     */
    public function store(Request $request, $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $this->authorize('update', $patient);

        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'type' => 'required|string',
        ]);

        $file = $request->file('file');
        $path = $file->store("patients/{$patientId}/documents", 'local');

        $document = Document::create([
            'patient_id' => $patientId,
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'mime_type' => $file->getMimeType(),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
        ]);

        return response()->json($document, 201);
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document)
    {
        $this->authorize('view', $document);

        return response()->json($document);
    }

    /**
     * Download the specified document.
     */
    public function download(Document $document)
    {
        $this->authorize('view', $document);

        $filePath = Storage::disk('local')->path($document->path);

        return response()->download($filePath, $document->name);
    }

    /**
     * Public, signed access to a document.
     */
    public function publicDocument(Request $request, Document $document)
    {
        if (!Storage::disk('local')->exists($document->path)) {
            abort(404, 'Dokument nebol nájdený');
        }

        $pdfPath = $this->service->getTravelDocumentPdfPath($document);
        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            abort(500, 'Chyba pri generovaní PDF dokumentu');
        }

        $filePath = Storage::disk('local')->path($pdfPath);
        $downloadName = pathinfo($document->name, PATHINFO_FILENAME) . '.pdf';

        if ($request->query('download') === '1') {
            return response()->download($filePath, $downloadName);
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }
    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'type' => 'sometimes|string',
            'name' => 'sometimes|string',
        ]);

        $document->update($validated);

        return response()->json($document);
    }

    /**
     * Delete the specified document.
     */
    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        $deleted = $this->service->deleteDocumentWithAssets($document);
        if (!$deleted) {
            \Log::error('Failed to delete document file', [
                'document_id' => $document->id,
                'path' => $document->path,
            ]);

            return response()->json([
                'error' => 'Nepodarilo sa odstrániť súbor z disku',
                'path' => $document->path,
            ], 500);
        }

        $document->delete();

        return response()->json(['message' => 'Dokument bol úspešne odstránený']);
    }

    /**
     * Delete multiple documents.
     */
    public function destroyMany(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['message' => 'Neboli poskytnuté žiadne ID'], 400);
        }

        $this->service->deleteManyDocumentsWithAssets($ids);

        return response()->json(['message' => 'Dokumenty boli úspešne odstránené']);
    }

    public function emailTravelDocuments(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $user = Auth::user();
        $userName = trim(implode(' ', array_filter([$user->title, $user->first_name, $user->last_name])));
        $companyName = $user->company?->name;

        $documents = $this->service->buildTravelDocumentLinks($validated['ids'], $user);

        if (empty($documents)) {
            return response()->json(['message' => 'Neboli nájdené žiadne dokumenty, ku ktorým máte prístup'], 404);
        }

        $to = $validated['email'];
        $subject = 'Cestovné dokumenty - ' . ($companyName ?: 'ADOcare');
        $viewData = [
            'recipientName' => $to,
            'senderName' => $userName,
            'companyName' => $companyName,
            'documents' => $documents,
        ];

        $this->service->sendEmail($to, $subject, 'emails.document_links', $viewData);

        return response()->json([
            'message' => 'Email bol úspešne odoslaný',
            'documents_count' => count($documents),
        ]);
    }

    public function getByType($patientId, $type)
    {
        $patient = Patient::findOrFail($patientId);
        $this->authorize('view', $patient);

        $documents = Document::where('patient_id', $patientId)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($documents);
    }

    /**
     * Check if a document for a given period already exists.
     * Supports two scenarios:
     * 1. Patient document: checks patient_id, user_id, type, and period
     * 2. User document: checks user_id, type, branch_id, and period
     */
    public function checkExists(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string',
                'date' => 'required|date',
                'patient_id' => 'nullable|numeric',
                'branch_id' => 'nullable|numeric',
            ]);

            return response()->json($this->service->documentExists($validated, Auth::id()));
        } catch (\Exception $e) {
            \Log::error('Document checkExists error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e,
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
