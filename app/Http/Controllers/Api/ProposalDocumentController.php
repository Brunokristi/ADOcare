<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Document;
use App\Models\Patient;
use App\Services\ProposalDocumentService;
use App\Http\Requests\StoreProposalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProposalDocumentController extends Controller
{
    public function __construct(private ProposalDocumentService $service)
    {
    }

    /**
        * Store a new proposal document.
        *
        * @group Documents
        * @bodyParam patient_id integer required Patient ID. Example: 1
        * @bodyParam medical_diagnosis_id integer nullable Diagnosis ID. Example: 2
        * @bodyParam nurse_diagnosis_id integer nullable Nurse diagnosis ID. Example: 3
        * @bodyParam date date required Document date. Example: 2024-01-01
        * @bodyParam epicrisis_description string required
        * @bodyParam care_plan string required
        * @bodyParam expected_duration string required
        * @bodyParam procedures array nullable Array of procedures
        * @response 201 {"document_id":123, "proposal": {"document_id":123}}
     */
    public function store(StoreProposalRequest $request)
    {
        [$document, $payload] = $this->service->createProposal($request->validated(), $request->user());

        return $this->success([
            'document_id' => $document->id,
            'proposal' => $payload,
        ], 'Návrh ošetrovateľskej starostlivosti bol úspešne vytvorený', 201);
    }

    /**
     * Show proposal payload for a document (route-model binding).
     *
     * @group Documents
     * @urlParam document int required Document ID. Example: 123
     * @response 200 {"document": {"id":123}, "proposal_data": {...}}
     */
    public function show(Document $document)
    {
        $document->loadMissing('patient');
        $payload = $this->service->getProposalPayload($document);
        if (! $payload) {
            return $this->error('Proposal data not found', 404);
        }

        return $this->success(['document' => $document, 'proposal_data' => $payload]);
    }

    /**
     * Return latest proposal for a patient (route-model binding).
     *
     * @group Documents
     * @urlParam patient int required Patient ID. Example: 1
     * @response 200 {"document_id":123, "proposal_data": {...}}
     */
    public function latestByPatient(Patient $patient)
    {
        $document = Document::where('patient_id', $patient->id)
            ->where('type', 'proposal')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $document) {
            return $this->error('No proposal found', 404);
        }

        $payload = $this->service->getProposalPayload($document);
        if (! $payload) {
            return $this->error('Proposal data not found', 404);
        }

        return $this->success(['document_id' => $document->id, 'proposal_data' => $payload]);
    }
}
