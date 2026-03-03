<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Services\KilometersBatchDocumentService;
use App\Services\PointsBatchDocumentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

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

        $kilometersQ = Document::query()
            ->where('type', 'kilometers_batch')
            ->whereHas('user', fn ($q) => $q->where('company_id', $companyId))
            ->with(['insuranceCompany:id,name', 'user:id,title,first_name,last_name', 'branch:id,address']);

        $pointsQ = Document::query()
            ->where('type', 'points_batch')
            ->whereHas('user', fn ($q) => $q->where('company_id', $companyId))
            ->with(['insuranceCompany:id,name', 'user:id,title,first_name,last_name', 'branch:id,address']);

        // Union the queries and order by created_at desc
        $q = $kilometersQ->union($pointsQ)
            ->orderByDesc('created_at');

        // Paginate
        $page_obj = \Illuminate\Pagination\Paginator::resolveCurrentPath();
        $items = $q->get();

        // Manually paginate
        $total = $items->count();
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageItems = $items->slice($offset, $perPage)->values();

        // Map and add amounts
        $items = $pageItems->map(function ($doc) {
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
}
