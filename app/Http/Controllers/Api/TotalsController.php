<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Total;
use Illuminate\Http\Request;

class TotalsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Total::query();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('insurance_company_id')) {
            $query->where('insurance_company_id', $request->insurance_company_id);
        }

        return response()->json([
            'data' => $query->with(['user', 'branch', 'insuranceCompany'])
                ->paginate($request->get('per_page', 15))
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
            ]
        );

        return response()->json(['data' => $total], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Total $total)
    {
        return response()->json(['data' => $total->load(['user', 'branch', 'insuranceCompany'])]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Total $total)
    {
        $validated = $request->validate([
            'points_total' => 'numeric|min:0',
            'kilometers_total' => 'numeric|min:0',
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
}
