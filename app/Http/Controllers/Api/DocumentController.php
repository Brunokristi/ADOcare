<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentController extends Controller
{
    public function indexTravelDocuments(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);

        $branchId = $request->input('branch_id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        $query = Document::query()
            ->whereIn('type', ['cp', 'dzc'])
            ->where('user_id', Auth::id());

        if ($branchId) {
            $query->where('branch_id', $branchId);
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

        // Parse comma-separated branch IDs or convert single ID to array
        $branchIdArray = [];
        if ($branchIds) {
            if (is_string($branchIds)) {
                $branchIdArray = array_map('intval', array_filter(explode(',', $branchIds)));
            } elseif (is_array($branchIds)) {
                $branchIdArray = array_map('intval', $branchIds);
            }
        }

        $query = Document::query()
            ->whereIn('type', ['cp', 'dzc']);

        if (!empty($branchIdArray)) {
            $query->whereIn('branch_id', $branchIdArray);
        }

        $documents = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

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
        
        // Delete documents from database
        Document::whereIn('id', $ids)->delete();
        
        return response()->json(['message' => 'Documents deleted successfully']);
    }

    /**
     * Get documents by type for a patient.
     */
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
}