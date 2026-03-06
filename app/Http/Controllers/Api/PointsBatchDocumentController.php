<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePointsBatchRequest;
use App\Models\Document;
use App\Services\PointsBatchDocumentService;
use Illuminate\Http\Request;

class PointsBatchDocumentController extends Controller
{
    public function __construct(private PointsBatchDocumentService $service)
    {
    }

    public function store(StorePointsBatchRequest $request)
    {
        [$document, $payload] = $this->service->createPointsBatch(
            $request->validated(),
            $request->user()
        );

        return $this->success([
            'document_id' => $document->id,
            'points_batch' => $payload,
        ], 'Body bodové dávky bola úspešne uložená', 201);
    }

    public function show(Document $document)
    {
        // optional: ensure correct type
        if ($document->type !== 'points_batch') {
            return $this->error('Invalid document type', 400);
        }

        $payload = $this->service->getPointsBatchPayload($document);
        if (! $payload) {
            return $this->error('Points batch data not found', 404);
        }

        return $this->success([
            'document' => $document,
            'points_batch' => $payload,
        ]);
    }

    public function index(Request $request)
    {
        $branchId = $request->integer('branch_id');
        $userId   = auth()->id();

        $q = Document::query()
            ->where('type', 'points_batch')
            ->with(['insuranceCompany:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at');

        $page = $q->paginate($request->integer('per_page', 25));

        $items = $page->getCollection()->map(function ($doc) {
            $doc->insurance_company_name = $doc->insuranceCompany?->name;
            
            // Load amount from payload meta
            $payload = $this->service->getPointsBatchPayload($doc);
            $doc->amount = data_get($payload, 'meta.amount', 0);
            
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