<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use App\Services\CPDocumentService;
use App\Services\DZCDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DocumentController extends Controller
{
    public function indexTravelDocuments(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);

        $branchId = $request->input('branch_id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;
        $period = $request->input('period');

        $query = Document::query()
            ->whereIn('type', ['cp', 'dzc'])
            ->where('user_id', Auth::id());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($period) {
            $query->where('period', Carbon::parse($period)->format('Y-m'));
        }

        $documents = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

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

        $query = Document::query()
            ->with([
                'user:id,title,first_name,last_name',
                'branch:id,address',
            ])
            ->whereIn('type', ['cp', 'dzc']);

        if (!empty($branchIdArray)) {
            $query->whereIn('branch_id', $branchIdArray);
        }

        if ($period) {
            $query->where('period', Carbon::parse($period)->format('Y-m'));
        }

        $documents = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $documents->getCollection()->transform(function ($doc) {
            $representative = $doc->user;
            $branch = $doc->branch;

            $userName = trim(implode(' ', array_filter([
                $representative?->title,
                $representative?->first_name,
                $representative?->last_name,
            ])));

            $branchAddress = $branch?->address; // or format it further if it's an object/array

            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'type' => $doc->type,
                'mime_type' => $doc->mime_type,
                'path' => $doc->path,
                'created_at' => $doc->created_at?->toDateTimeString(),
                'updated_at' => $doc->updated_at?->toDateTimeString(),
                'period' => $doc->period,

                // what your frontend expects:
                'created_by_user' => $userName ?: null,
                'created_by_branch' => $branchAddress ?: null,
            ];
        });

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

        return Storage::disk('local')->download($document->path, $document->name);
    }

    /**
     * Update the specified document.
     */
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
        
        $this->deleteScanAssetsIfAny($document);

        if (Storage::disk('local')->exists($document->path)) {
            $deleted = Storage::disk('local')->delete($document->path);
            if (!$deleted) {
                \Log::error('Failed to delete document file', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                ]);
                return response()->json([
                    'error' => 'Failed to delete file from disk',
                    'path' => $document->path,
                ], 500);
            }
        } else {
            \Log::warning('Document file not found on disk', [
                'document_id' => $document->id,
                'expected_path' => $document->path,
            ]);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    /**
     * Delete multiple documents.
     */
    public function destroyMany(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            return response()->json(['message' => 'No IDs provided'], 400);
        }
        
        $documents = Document::whereIn('id', $ids)->get();
        
        foreach ($documents as $document) {

            $this->deleteScanAssetsIfAny($document);

            if (Storage::disk('local')->exists($document->path)) {
                $deleted = Storage::disk('local')->delete($document->path);
                if (!$deleted) {
                    \Log::error('Failed to delete document file (batch)', [
                        'document_id' => $document->id,
                        'path' => $document->path,
                    ]);
                }
            } else {
                \Log::warning('Document file not found on disk (batch)', [
                    'document_id' => $document->id,
                    'expected_path' => $document->path,
                ]);
            }
        }
        
        Document::whereIn('id', $ids)->delete();
        
        return response()->json(['message' => 'Documents deleted successfully']);
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


        $companyId = Auth::user()?->company_id;
        $compnayName = $user->company?->name;

        $documentsQuery = Document::query()
            ->whereIn('id', $validated['ids'])
            ->whereIn('type', ['cp', 'dzc']);

        if ($companyId) {
            $documentsQuery->whereHas('user', fn ($q) => $q->where('company_id', $companyId));
        }

        $documents = $documentsQuery->get();

        if ($documents->isEmpty()) {
            return response()->json(['message' => 'No valid documents found'], 404);
        }

        $attachments = [];
        foreach ($documents as $document) {
            $pdfAttachment = $this->buildTravelPdfAttachment($document);
            if ($pdfAttachment) {
                $attachments[] = $pdfAttachment;
            }
        }

        if (empty($attachments)) {
            return response()->json(['message' => 'No readable document files found for attachments'], 422);
        }

        $to = $validated['email'];
        $subject = 'Cestovné dokumenty - ' . ($compnayName ?: 'ADOcare');
        $body = "Dobrý deň,\n\nV prílohe posielame cestovné dokumenty.\n\nS pozdravom,\n$userName";

        Mail::raw($body, function ($message) use ($to, $subject, $attachments) {
            $message->to($to)->subject($subject);

            foreach ($attachments as $attachment) {
                $message->attachData($attachment['data'], $attachment['name'], [
                    'as' => $attachment['name'],
                    'mime' => $attachment['mime'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Email sent successfully',
            'attachments_count' => count($attachments),
        ]);
    }

    private function deleteScanAssetsIfAny(Document $document): void
    {
        if ($document->type !== 'scan') {
            return;
        }

        $sessionId = null;

        if (!$sessionId && Storage::disk('local')->exists($document->path)) {
            try {
                $raw = Storage::disk('local')->get($document->path);
                $json = json_decode($raw, true);
                $sessionId = (int) ($json['scan_session_id'] ?? 0);
            } catch (\Throwable $e) {
                \Log::warning('Failed to parse scan document JSON for cleanup', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$sessionId) return;

        $scanDir = "scans/{$sessionId}";

        if (Storage::disk('local')->exists($scanDir)) {
            Storage::disk('local')->deleteDirectory($scanDir);
        }
    }

    private function buildAttachmentFilename(Document $document): string
    {
        $rawName = trim((string) ($document->name ?: ('document_' . $document->id)));
        $base = pathinfo($rawName, PATHINFO_FILENAME);
        $base = $base !== '' ? $base : ('document_' . $document->id);

        $ext = pathinfo($rawName, PATHINFO_EXTENSION);
        if ($ext === '' && $document->path) {
            $ext = pathinfo((string) $document->path, PATHINFO_EXTENSION);
        }

        if ($ext === '') {
            $ext = match ($document->mime_type) {
                'application/pdf' => 'pdf',
                'application/json' => 'json',
                default => 'bin',
            };
        }

        return $base . '.' . $ext;
    }

    private function buildTravelPdfAttachment(Document $document): ?array
    {
        if ($document->type === 'cp') {
            $payload = app(CPDocumentService::class)->getCpPayload($document);
            if (!$payload) {
                return null;
            }

            $representativeId = isset($payload['representative_id']) ? (int) $payload['representative_id'] : null;
            $signatureDataUri = $this->loadUserSignatureDataUri($representativeId);

            $pdf = Pdf::loadView('pdf.travel_cp', [
                'cpData' => $payload,
                'signatureDataUri' => $signatureDataUri,
            ])->setPaper('a4', 'portrait');

            $filename = $this->buildTravelPdfFilename('CP', $payload, $document);

            return [
                'data' => $pdf->output(),
                'name' => $filename,
                'mime' => 'application/pdf',
            ];
        }

        if ($document->type === 'dzc') {
            $payload = app(DZCDocumentService::class)->getDzcPayload($document);
            if (!$payload) {
                return null;
            }

            $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
            $signatureDataUri = $this->loadUserSignatureDataUri($userId);

            $pdf = Pdf::loadView('pdf.travel_dzc', [
                'dzcData' => $payload,
                'signatureDataUri' => $signatureDataUri,
            ])->setPaper('a4', 'portrait');

            $filename = $this->buildTravelPdfFilename('DZP', $payload, $document);

            return [
                'data' => $pdf->output(),
                'name' => $filename,
                'mime' => 'application/pdf',
            ];
        }

        return null;
    }

    private function loadUserSignatureDataUri(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        $signaturePath = $user?->signature_path;

        if (!$signaturePath || !Storage::disk('local')->exists($signaturePath)) {
            return null;
        }

        $binary = Storage::disk('local')->get($signaturePath);
        if ($binary === null || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }

    private function buildTravelPdfFilename(string $prefix, array $payload, Document $document): string
    {
        $period = (string) ($payload['period'] ?? $document->period ?? 'unknown-period');

        if ($period === 'unknown-period') {
            $month = (string) ($payload['month'] ?? '');
            $year = (string) ($payload['year'] ?? '');
            if ($month !== '' && $year !== '') {
                $period = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            }
        }

        $rawUserName = trim(implode(' ', array_filter([
            $document->user->last_name ?? null,
        ])));

        if ($rawUserName === '') {
            $rawUserName = 'unknown-user';
        }

        $normalizedUserName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $rawUserName);

        if ($normalizedUserName === false || trim($normalizedUserName) === '') {
            $normalizedUserName = $rawUserName;
        }

        $safePrefix = $this->sanitizeFilenamePart($prefix);
        $safePeriod = $this->sanitizeFilenamePart($period);
        $safeUserName = $this->sanitizeFilenamePart($normalizedUserName);

        return $safePrefix . '_' . $safePeriod . '_' . $safeUserName . '.pdf';
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = preg_replace('/[^A-Za-z0-9_\-.]/', '', $value) ?? $value;

        if ($value === '') {
            return 'unknown';
        }

        return $value;
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

            // Extract period (Y-m format) from provided date
            $date = new \DateTime($validated['date']);
            $period = $date->format('Y-m');

            $type = $validated['type'];
            $userId = Auth::id();

            $document = null;
            $patientId = $validated['patient_id'] ?? null;
            $branchId = $validated['branch_id'] ?? null;

            if ($patientId) {
                // Patient document: check patient_id, user_id, type, and period
                $document = Document::where('patient_id', $patientId)
                    ->where('user_id', $userId)
                    ->where('type', $type)
                    ->where('period', $period)
                    ->first();
            } else {
                // User document: check user_id, type, branch_id, and period
                $query = Document::where('user_id', $userId)
                    ->where('type', $type)
                    ->where('period', $period);

                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }

                $document = $query->first();
            }

            return response()->json([
                'exists' => $document !== null,
                'document_id' => $document?->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Document checkExists error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e,
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}