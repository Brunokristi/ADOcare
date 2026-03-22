<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveDocumentRequest;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use App\Services\LeaveDocumentService;

/**
 * Handles leave document endpoints.
 */
class LeaveDocumentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private LeaveDocumentService $service)
    {
    }

    /**
     * Store a new leave document.
     */
    public function store(StoreLeaveDocumentRequest $request)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $this->error('Používateľ nie je autentifikovaný', 401);
        }

        $document = $this->service->create($request->validated(), $user);

        return $this->success([
            'document_id' => $document->id,
        ], 'Prepúšťacia správa bola úspešne vytvorená', 201);
    }

    /**
     * Show leave document payload for the provided document id.
     */
    public function show(Document $document)
    {
        $this->authorize('view', $document);

        $document->loadMissing(['patient']);
        $leaveFile = $this->service->findLeaveFileForDocument($document);

        if (!$leaveFile) {
            return $this->error('Dáta prepúšťacej správy sa nenašli', 404);
        }

        return $this->success([
            'document' => $document,
            'leave_data' => $leaveFile,
        ], 'Prepúšťacia správa bola načítaná');
    }

    /**
     * Show latest leave document payload by patient id.
     */
    public function latestByPatient(Patient $patient)
    {
        $this->authorize('view', $patient);

        $document = $this->service->findLatestDocumentByPatientId($patient->id);

        if (!$document) {
            return $this->error('Prepúšťacia správa sa nenašla', 404);
        }

        $leaveFile = $this->service->findLeaveFileForDocument($document);

        if (!$leaveFile) {
            return $this->error('Dáta prepúšťacej správy sa nenašli', 404);
        }

        return $this->success([
            'document_id' => $document->id,
            'leave_data' => $leaveFile,
        ], 'Najnovšia prepúšťacia správa bola načítaná');
    }
}
