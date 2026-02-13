<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;

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

        $plans = Plan::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get();
        return $this->success($plans, 'Plans retrieved');
    }
}
