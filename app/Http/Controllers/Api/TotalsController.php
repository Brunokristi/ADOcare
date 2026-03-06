<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Total;
use App\Models\User;
use App\Models\Branch;
use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use App\Models\Document;

class TotalsController extends Controller
{
    /**
     * Display a listing of the resource.
     * Aggregates batch documents by user/insurance_company/month with price_paid
     */
    public function index(Request $request)
    {
        $companyId = auth()->user()?->company_id;

        $aggregated = Document::query()
            ->whereIn('type', ['points_batch', 'kilometers_batch'])
            ->whereHas('user', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->select('user_id', 'insurance_company_id', 'branch_id', 'period as month')
            ->distinct()
            ->get();

        $results = [];
        foreach ($aggregated as $row) {
            $batchData = $this->getAggregatedBatchTotals(
                $row->user_id,
                $row->insurance_company_id,
                $row->branch_id,
                $row->month
            );

            // Find or fetching related models
            $user = $row->user_id ? User::find($row->user_id) : null;
            $branch = $row->branch_id ? Branch::find($row->branch_id) : null;
            $insuranceCompany = $row->insurance_company_id ? InsuranceCompany::find($row->insurance_company_id) : null;

            // Find or create Total record for price_paid
            $total = Total::where('user_id', $row->user_id)
                ->where('insurance_company_id', $row->insurance_company_id)
                ->where('branch_id', $row->branch_id)
                ->where('month', $row->month)
                ->first();

            $results[] = [
                'id' => $total?->id ?? 'temp_' . md5("{$row->user_id}:{$row->insurance_company_id}:{$row->month}"),
                'user_id' => $row->user_id,
                'insurance_company_id' => $row->insurance_company_id,
                'branch_id' => $row->branch_id,
                'month' => $row->month,
                'points_generated' => (float)($batchData['points'] ?? 0),
                'kilometers_generated' => (float)($batchData['kilometers'] ?? 0),
                'points_total' => 0,
                'kilometers_total' => 0,
                'price_paid' => $total ? (float)$total->price_paid : null,
                'user' => $user ? [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ] : null,
                'branch' => $branch ? [
                    'id' => $branch->id,
                    'address' => $branch->address,
                ] : null,
                'insurance_company' => $insuranceCompany ? [
                    'id' => $insuranceCompany->id,
                    'name' => $insuranceCompany->name,
                ] : null,
            ];
        }

        // Apply filters if provided
        if ($request->has('user_id')) {
            $results = array_filter($results, fn($r) => $r['user_id'] == $request->user_id);
        }

        if ($request->has('branch_id')) {
            $results = array_filter($results, fn($r) => $r['branch_id'] == $request->branch_id);
        }

        if ($request->has('month')) {
            $results = array_filter($results, fn($r) => $r['month'] == $request->month);
        }

        if ($request->has('insurance_company_id')) {
            $results = array_filter($results, fn($r) => $r['insurance_company_id'] == $request->insurance_company_id);
        }

        // Paginate results
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $offset = ($page - 1) * $perPage;
        $total = count($results);
        $lastPage = ceil($total / $perPage);
        $paginatedResults = array_slice($results, $offset, $perPage);

        return response()->json([
            'data' => [
                'data' => $paginatedResults,
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => (int)$total,
                'last_page' => (int)$lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m',
            'branch_id' => 'nullable|exists:branches,id',
            'insurance_company_id' => 'required|exists:insurance_companies,id',
            'points_total' => 'numeric|min:0',
            'kilometers_total' => 'numeric|min:0',
            'price_paid' => 'nullable|numeric|min:0',
        ]);

        $total = Total::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'month' => $validated['month'],
                'branch_id' => $validated['branch_id'] ?? null,
                'insurance_company_id' => $validated['insurance_company_id'],
            ],
            [
                'points_total' => $validated['points_total'] ?? 0,
                'kilometers_total' => $validated['kilometers_total'] ?? 0,
                'price_paid' => $validated['price_paid'] ?? null,
            ]
        );

        return response()->json(['data' => $total], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Total $total)
    {
        $total->load(['user', 'branch', 'insuranceCompany']);
        
        // Add batch totals
        $batchesData = $this->getBatchTotals($total);
        $total->points_generated = $batchesData['points'] ?? null;
        $total->kilometers_generated = $batchesData['kilometers'] ?? null;
        
        return response()->json(['data' => $total]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Total $total)
    {
        $validated = $request->validate([
            'points_total' => 'numeric|min:0',
            'kilometers_total' => 'numeric|min:0',
            'price_paid' => 'nullable|numeric|min:0',
        ]);

        $total->update($validated);

        return response()->json(['data' => $total]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Total $total)
    {
        $total->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk delete multiple totals.
     */
    public function destroyMany(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:totals,id',
        ]);

        Total::whereIn('id', $validated['ids'])->delete();

        return response()->json(null, 204);
    }

    /**
     * Get aggregated batch totals for a user/insurance_company/branch/month
     * by summing all batch documents for that combination
     */
    private function getAggregatedBatchTotals($userId, $insuranceCompanyId, $branchId, $month)
    {
        $companyId = auth()->user()?->company_id;
        $points = 0;
        $kilometers = 0;

        try {
            $documents = Document::query()
                ->where('user_id', $userId)
                ->where('insurance_company_id', $insuranceCompanyId)
                ->where('branch_id', $branchId)
                ->where('period', $month)
                ->whereIn('type', ['points_batch', 'kilometers_batch'])
                ->whereHas('user', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })
                ->get();

            foreach ($documents as $doc) {
                $data = $this->parseDocumentJson($doc->path);
                if (is_array($data)) {
                    $amount = (float)(data_get($data, 'meta.amount', 0));
                    
                    if ($doc->type === 'points_batch') {
                        $points += $amount;
                    } elseif ($doc->type === 'kilometers_batch') {
                        $kilometers += $amount;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to get aggregated batch totals for user=$userId, month=$month: " . $e->getMessage());
        }

        return ['points' => $points, 'kilometers' => $kilometers];
    }

    /**
     * Get batch totals (generated amounts) for a given Total
     * by summing values from the documents table
     */
    private function getBatchTotals(Total $total)
    {
        $result = ['points' => null, 'kilometers' => null];

        if (!$total->month) {
            return $result;
        }

        try {
            // Get points_batch total
            $pointsDoc = \DB::table('documents')
                ->where('type', 'points_batch')
                ->where('user_id', $total->user_id)
                ->where('branch_id', $total->branch_id)
                ->where('insurance_company_id', $total->insurance_company_id)
                ->where('period', $total->month)
                ->first();

            if ($pointsDoc && $pointsDoc->path) {
                $pointsData = $this->parseDocumentJson($pointsDoc->path);
                if (is_array($pointsData)) {
                    $result['points'] = (float) data_get($pointsData, 'meta.amount', 0);
                }
            }

            // Get kilometers_batch total
            $kmDoc = \DB::table('documents')
                ->where('type', 'kilometers_batch')
                ->where('user_id', $total->user_id)
                ->where('branch_id', $total->branch_id)
                ->where('insurance_company_id', $total->insurance_company_id)
                ->where('period', $total->month)
                ->first();

            if ($kmDoc && $kmDoc->path) {
                $kmData = $this->parseDocumentJson($kmDoc->path);
                if (is_array($kmData)) {
                    $result['kilometers'] = (float) data_get($kmData, 'meta.amount', 0);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get batch totals for Total ' . $total->id . ': ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Parse JSON from a document file path
     */
    private function parseDocumentJson(string $path)
    {
        try {
            if (\Storage::disk('local')->exists($path)) {
                return json_decode(\Storage::disk('local')->get($path), true);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to read document JSON at ' . $path . ': ' . $e->getMessage());
        }

        return null;
    }
}
