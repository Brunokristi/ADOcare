<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Services\KilometersBatchDocumentService;
use App\Services\PointsBatchDocumentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
}
