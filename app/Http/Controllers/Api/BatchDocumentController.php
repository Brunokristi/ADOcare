<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Services\KilometersBatchDocumentService;
use App\Services\PointsBatchDocumentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BatchDocumentController extends Controller
{
    public function __construct(
        private KilometersBatchDocumentService $kilometerService,
        private PointsBatchDocumentService $pointsService,
    ) {
    }

    /**
     * Get all batch documents (kilometers_batch and points_batch) for a company
     */
    public function indexByCompany(Request $request)
    {
        $companyId = auth()->user()?->company_id;
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 25);

        $items = Document::query()
            ->whereIn('type', ['kilometers_batch', 'points_batch'])
            ->whereHas('user', fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('period'), fn ($q) => $q->where('period', Carbon::parse($request->input('period'))->format('Y-m')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('period', '>=', Carbon::parse($request->input('date_from'))->format('Y-m')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('period', '<=', Carbon::parse($request->input('date_to'))->format('Y-m')))
            ->with(['insuranceCompany:id,name', 'user:id,title,first_name,last_name', 'branch:id,address'])
            ->get()
            ->map(function ($doc) {
            $doc->insurance_company_name = $doc->insuranceCompany?->name;
            $doc->branch_address = $doc->branch?->address;
            
            $userName = [];
            if ($doc->user?->title) {
                $userName[] = $doc->user->title;
            }
            if ($doc->user?->first_name) {
                $userName[] = $doc->user->first_name;
            }
            if ($doc->user?->last_name) {
                $userName[] = $doc->user->last_name;
            }
            $doc->created_by_user = implode(' ', $userName) ?: 'Neznámy';

            if ($doc->type === 'kilometers_batch') {
                $payload = $this->kilometerService->getKilometersBatchPayload($doc);
            } else {
                $payload = $this->pointsService->getPointsBatchPayload($doc);
            }
            $doc->amount = data_get($payload, 'meta.amount', 0);

            return $doc;
        })->values();

        $items = $this->applyDocumentSort($items, $request->input('sort', '-created_at'));

        $total = $items->count();
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $items = $items->slice($offset, $perPage)->values();

        return $this->success([
            'items' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ]);
    }

    public function aggregatedByBranch(Request $request)
    {
        $companyId = auth()->user()?->company_id;
        $month = $request->string('month')->toString();

        \Log::info('[MonthStats] aggregatedByBranch called', [
            'company_id' => $companyId,
            'month' => $month,
        ]);

        $documents = $this->getCompanyMonthBatchDocuments($companyId, $month);

        \Log::info('[MonthStats] Documents found', [
            'count' => $documents->count(),
            'sample' => $documents->take(2)->map(fn($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'branch_id' => $d->branch_id,
                'created_at' => $d->created_at,
            ])->toArray(),
        ]);

        $grouped = [];
        foreach ($documents as $doc) {
            $key = $doc->branch_id ?? 'unknown';

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'branch_id' => $doc->branch_id,
                    'branch_name' => $doc->branch?->address ?? 'Neznáma pobočka',
                    'total_points' => 0.0,
                    'total_kilometers' => 0.0,
                    'total_amount' => 0.0,
                ];
            }

            $amount = $this->getDocumentAmount($doc);
            if ($doc->type === 'points_batch') {
                $grouped[$key]['total_points'] += $amount;
            }
            if ($doc->type === 'kilometers_batch') {
                $grouped[$key]['total_kilometers'] += $amount;
            }
            $grouped[$key]['total_amount'] += $amount;
        }

        $result = array_values($grouped);
        \Log::info('[MonthStats] aggregatedByBranch result', [
            'branches_count' => count($result),
            'sample' => array_slice($result, 0, 2),
        ]);

        return $this->success([
            'items' => $result,
        ]);
    }

    public function aggregatedByUser(Request $request)
    {
        $companyId = auth()->user()?->company_id;
        $month = $request->string('month')->toString();

        \Log::info('[MonthStats] aggregatedByUser called', [
            'company_id' => $companyId,
            'month' => $month,
        ]);

        $documents = $this->getCompanyMonthBatchDocuments($companyId, $month);

        \Log::info('[MonthStats] Documents found', [
            'count' => $documents->count(),
            'sample' => $documents->take(2)->map(fn($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'user_id' => $d->user_id,
                'created_at' => $d->created_at,
            ])->toArray(),
        ]);

        $grouped = [];
        foreach ($documents as $doc) {
            $key = $doc->user_id ?? 'unknown';

            if (!isset($grouped[$key])) {
                $nameParts = array_filter([
                    $doc->user?->title,
                    $doc->user?->first_name,
                    $doc->user?->last_name,
                ]);

                $grouped[$key] = [
                    'user_id' => $doc->user_id,
                    'user_name' => implode(' ', $nameParts) ?: 'Neznámy užívateľ',
                    'total_points' => 0.0,
                    'total_kilometers' => 0.0,
                    'total_amount' => 0.0,
                ];
            }

            $amount = $this->getDocumentAmount($doc);
            if ($doc->type === 'points_batch') {
                $grouped[$key]['total_points'] += $amount;
            }
            if ($doc->type === 'kilometers_batch') {
                $grouped[$key]['total_kilometers'] += $amount;
            }
            $grouped[$key]['total_amount'] += $amount;
        }

        $result = array_values($grouped);
        \Log::info('[MonthStats] aggregatedByUser result', [
            'users_count' => count($result),
            'sample' => array_slice($result, 0, 2),
        ]);

        return $this->success([
            'items' => $result,
        ]);
    }

    private function getCompanyMonthBatchDocuments(?int $companyId, string $month)
    {
        if (!$companyId || !$month) {
            return collect();
        }

        \Log::info('[MonthStats] getCompanyMonthBatchDocuments', [
            'company_id' => $companyId,
            'month' => $month,
        ]);

        $docs = Document::query()
            ->whereIn('type', ['points_batch', 'kilometers_batch'])
            ->whereHas('user', fn ($q) => $q->where('company_id', $companyId))
            ->where('period', $month)
            ->with(['user:id,title,first_name,last_name', 'branch:id,address'])
            ->get();

        \Log::info('[MonthStats] Query result', [
            'count' => $docs->count(),
            'sample_ids' => $docs->take(3)->pluck('id')->toArray(),
        ]);

        return $docs;
    }

    private function getDocumentAmount(Document $doc): float
    {
        if ($doc->type === 'kilometers_batch') {
            $payload = $this->kilometerService->getKilometersBatchPayload($doc);
        } else {
            $payload = $this->pointsService->getPointsBatchPayload($doc);
        }

        return (float) data_get($payload, 'meta.amount', 0);
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
            'type' => mb_strtolower((string) ($doc->type ?? '')),
            'subtype' => mb_strtolower((string) ($doc->subtype ?? '')),
            'created_at' => $doc->created_at ? strtotime((string) $doc->created_at) ?: 0 : 0,
            'insurance_company_name' => mb_strtolower((string) ($doc->insurance_company_name ?? '')),
            'created_by_user' => mb_strtolower((string) ($doc->created_by_user ?? '')),
            'branch_address' => mb_strtolower((string) ($doc->branch_address ?? '')),
            default => $doc->{$field} ?? '',
        };
    }
}
