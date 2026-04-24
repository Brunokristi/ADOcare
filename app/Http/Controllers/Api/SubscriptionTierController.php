<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\SubscriptionTier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:subscription_tiers,name'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'users_limit' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (!array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = (int) SubscriptionTier::max('sort_order') + 1;
        }

        $tier = SubscriptionTier::create($validated);

        return $this->success($tier, 'Created', Response::HTTP_CREATED);
    }

    public function update(Request $request, SubscriptionTier $subscriptionTier)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:subscription_tiers,name,' . $subscriptionTier->id],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'users_limit' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $subscriptionTier->update($validated);

        return $this->success($subscriptionTier->fresh()->loadCount('companies'), 'Updated');
    }

    public function destroy(SubscriptionTier $subscriptionTier)
    {
        if ($subscriptionTier->companies()->count() > 0) {
            return $this->error('Cannot delete tier assigned to companies', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subscriptionTier->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    public function destroyMany(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:subscription_tiers,id'],
        ]);

        $ids = $validated['ids'];

        $inUse = SubscriptionTier::whereIn('id', $ids)
            ->whereHas('companies')
            ->exists();

        if ($inUse) {
            return $this->error('Cannot delete tiers assigned to companies', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        SubscriptionTier::whereIn('id', $ids)->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
