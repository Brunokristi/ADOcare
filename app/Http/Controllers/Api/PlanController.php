<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * GET /v1/plans
     * Returns all plans
     * 
     * @group Plans
     * @response 200 {"data": [{"id": 1, "name": "Plan name", "text": "Plan text"}]}
     */
    public function index()
    {
        $user = request()->user();
        $companyId = $user->company_id;

        $perPage = (int) request()->input('per_page', 25);

        $plans = Plan::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->paginate($perPage);
        
        return response()->json($plans);
    }

    /**
     * POST /v1/plans
     * Create a new plan
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'text' => 'required|string',
        ]);

        $maxSortOrder = Plan::where('company_id', $companyId)->max('sort_order') ?? 0;

        $plan = Plan::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'text' => $validated['text'],
            'sort_order' => $maxSortOrder + 1,
        ]);

        return response()->json($plan, 201);
    }

    /**
     * GET /v1/plans/{id}
     * Get a specific plan
     */
    public function show(Plan $plan)
    {
        return response()->json($plan);
    }

    /**
     * PUT /v1/plans/{id}
     * Update a plan
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'text' => 'sometimes|string',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $plan->update($validated);

        return response()->json($plan);
    }

    /**
     * DELETE /v1/plans/{id}
     * Delete a single plan
     */
    public function destroy(Plan $plan)
    {
        $plan->delete();

        return response()->json(['message' => 'Plan deleted successfully']);
    }

    /**
     * DELETE /v1/plans
     * Delete multiple plans
     */
    public function destroyMany(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            return response()->json(['message' => 'No IDs provided'], 400);
        }
        
        Plan::whereIn('id', $ids)->delete();
        
        return response()->json(['message' => 'Plans deleted successfully']);
    }
}