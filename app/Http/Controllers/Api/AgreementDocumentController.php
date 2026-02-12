<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Document;
use App\Models\Branch;
use App\Services\AgreementDocumentService;
use App\Http\Requests\StoreAgreementRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class AgreementDocumentController extends Controller
{
    public function __construct(private AgreementDocumentService $service)
    {
    }

    /**
     * Store a new agreement document and its JSON payload.
     *
     * @param  \App\Http\Requests\StoreAgreementRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreAgreementRequest $request)
    {
        [$document, $payload] = $this->service->createAgreement($request->validated(), $request->user());

        return $this->success([
            'document_id' => $document->id,
            'agreement' => $payload,
        ], 'Dohoda o poskytovaní zdravotnej starostlivosti bola úspešne vytvorená', 201);
    }

    /**
     * Return agreement payload for a document.
     * Uses route-model binding (accepts `Document $document` instead of an id).
     */
    public function show(Document $document)
    {
        $document->loadMissing('patient');

        $payload = $this->service->getAgreementPayload($document);
        if (! $payload) {
            return $this->error('Agreement data not found', 404);
        }

        return $this->success([
            'document' => $document,
            'agreement_data' => $payload,
        ]);
    }
}
