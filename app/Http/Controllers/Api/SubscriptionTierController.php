<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\SubscriptionTier;
use Illuminate\Http\Request;

/**
 * Read-only legacy/historical subscription tier reference data.
 * Plan/price management now belongs to StudioKristian - this controller no
 * longer supports creating, updating or deleting tiers.
 */
class SubscriptionTierController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = SubscriptionTier::query()->withCount('companies');

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['name', 'description'],
            allowedFilters: ['is_active'],
            defaults: [
                'sort' => 'sort_order,name',
            ]
        );

        return $this->success(new BaseCollection($results), 'Subscription tiers retrieved');
    }

    public function show(SubscriptionTier $subscriptionTier)
    {
        return $this->success($subscriptionTier->loadCount('companies'), 'Subscription tier retrieved');
    }
}
