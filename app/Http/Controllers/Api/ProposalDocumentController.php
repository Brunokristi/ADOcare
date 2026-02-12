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
