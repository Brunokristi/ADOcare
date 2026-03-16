<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKilometersBatchRequest;
use App\Models\Document;
use App\Services\KilometersBatchDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        $period = $request->string('period')->toString();
        $userId   = auth()->id();

        $items = Document::query()
            ->where('type', 'kilometers_batch')
            ->with(['insuranceCompany:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($period !== '', fn ($q) => $q->where('period', $period))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->map(function ($doc) {
            $doc->insurance_company_name = $doc->insuranceCompany?->name;
            
            // Load amount from payload meta
            $payload = $this->service->getKilometersBatchPayload($doc);
            $doc->amount = data_get($payload, 'meta.amount', 0);
            
            return $doc;
        })->values();

        $items = $this->applyDocumentSort($items, $request->input('sort', '-created_at'));

        $perPage = $request->integer('per_page', 25);
        $page = max(1, $request->integer('page', 1));
        $total = $items->count();
        $lastPage = (int) ceil($total / $perPage);
        $items = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return $this->success([
            'items' => $items,                 // ✅ array of rows
            'meta'  => [                       // ✅ pagination info
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
        ]);
    }

    private function applyDocumentSort(Collection $items, ?string $sort): Collection
    {
        $sortParts = array_filter(explode(',', $sort ?: '-created_at'));

        if (empty($sortParts)) {
            return $items->values();
        }

        foreach (array_reverse($sortParts) as $part) {
            $direction = str_starts_with($part, '-') ? 'desc' : 'asc';
            $field = ltrim($part, '-');

            $items = $items->sortBy(
                fn (Document $doc) => $this->normalizeSortableValue($doc, $field),
                SORT_NATURAL | SORT_FLAG_CASE,
                $direction === 'desc'
            )->values();
        }

        return $items;
    }

    private function normalizeSortableValue(Document $doc, string $field): int|float|string
    {
        return match ($field) {
            'insurance_company_name' => mb_strtolower((string) ($doc->insurance_company_name ?? '')),
            'subtype' => mb_strtolower((string) ($doc->subtype ?? '')),
            'period' => (string) ($doc->period ?? ''),
            'name' => mb_strtolower((string) ($doc->name ?? '')),
            'updated_at' => $doc->updated_at ? strtotime((string) $doc->updated_at) ?: 0 : 0,
            'created_at' => $doc->created_at ? strtotime((string) $doc->created_at) ?: 0 : 0,
            default => $doc->{$field} ?? '',
        };
    }
}