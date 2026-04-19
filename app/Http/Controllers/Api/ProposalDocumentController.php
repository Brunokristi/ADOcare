<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Document;
use App\Models\Patient;
use App\Services\ProposalDocumentService;
use App\Services\ProposalOcrPrefillService;
use App\Http\Requests\StoreProposalRequest;
use Illuminate\Http\Request;

class ProposalDocumentController extends Controller
{
    public function __construct(
        private ProposalDocumentService $service,
        private ProposalOcrPrefillService $ocrPrefillService,
    )
    {
    }

    /**
        * Store a new proposal document.
        *
        * @group Documents
        * @bodyParam patient_id integer required Patient ID. Example: 1
        * @bodyParam medical_diagnosis_ids integer[] nullable Array of medical diagnosis IDs. Example: [1, 2]
        * @bodyParam nurse_diagnosis_ids integer[] nullable Array of nurse diagnosis IDs. Example: [3, 4]
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

    /**
     * Return availability of OCR prefill from the latest scan for patient.
     *
     * @group Documents
     * @urlParam patient int required Patient ID. Example: 1
     */
    public function ocrPrefillAvailability(Patient $patient)
    {
        $availability = $this->ocrPrefillService->getLatestScanAvailability($patient);

        return $this->success($availability, 'Dostupnosť OCR prefillu bola načítaná.');
    }

    /**
     * Build proposal prefill from latest patient scan OCR via Gemini.
     *
     * @group Documents
     * @urlParam patient int required Patient ID. Example: 1
     */
    public function prefillFromLatestScan(Patient $patient)
    {
        try {
            $data = $this->ocrPrefillService->buildPrefillFromLatestScan($patient);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);
            return $this->error('Nepodarilo sa spracovať OCR dokument pomocou AI.', 500);
        }

        return $this->success($data, 'Prefill bol úspešne vygenerovaný z OCR dokumentu.');
    }
}
