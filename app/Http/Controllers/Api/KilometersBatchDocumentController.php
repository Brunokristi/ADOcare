<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKilometersBatchRequest;
use App\Models\Document;
use App\Services\KilometersBatchDocumentService;
use Illuminate\Http\Request;

class KilometersBatchDocumentController extends Controller
{
    public function __construct(private KilometersBatchDocumentService $service)
    {
    }

    public function store(StoreKilometersBatchRequest $request)
    {
        [$document, $payload] = $this->service->createKilometersBatch(
            $request->validated(),
            $request->user()
        );

        return $this->success([
            'document_id' => $document->id,
            'kilometers_batch' => $payload,
        ], 'Kilometre dávka bola úspešne uložená', 201);
    }

    public function show(Document $document)
    {
        // optional: ensure correct type
        if ($document->type !== 'kilometers_batch') {
            return $this->error('Invalid document type', 400);
        }

        $payload = $this->service->getKilometersBatchPayload($document);
        if (! $payload) {
            return $this->error('Kilometers batch data not found', 404);
        }

        return $this->success([
            'document' => $document,
            'kilometers_batch' => $payload,
        ]);
    }

    public function index(Request $request)
    {
        $branchId = $request->integer('branch_id');
        $userId   = auth()->id();

        $q = Document::query()
            ->where('type', 'kilometers_batch')
            ->with(['insuranceCompany:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at');

        $page = $q->paginate($request->integer('per_page', 25));

        $items = $page->getCollection()->map(function ($doc) {
            $doc->insurance_company_name = $doc->insuranceCompany?->name;
            return $doc;
        })->values();

        return $this->success([
            'items' => $items,                 // ✅ array of rows
            'meta'  => [                       // ✅ pagination info
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }
}