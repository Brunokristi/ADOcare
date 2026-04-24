<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Requests\DestroyManyCompanyRequest;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\UserCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanySubscriptionPaidMonth;
use App\Models\CompanySubscriptionPayment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use \App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    /**
     * List companies
     *
     * @group Companies
     * @queryParam q string Search query. Example: "Acme"
     * @response 200 {
     *  "data": [ {"id":1, "name":"Acme"} ],
     *  "meta": {"total":1}
     * }
     */
    public function index(Request $request)
    {
        $query = Company::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Companies retrieved');
    }

    /**
     * Create a company
     *
     * @group Companies
     * @bodyParam name string required Company name. Example: "Acme"
     * @response 201 {"id":1, "name":"Acme"}
     */
    public function store(\App\Http\Requests\StoreCompanyRequest $request)
    {
        $item = Company::create($request->validated());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    /**
     * Get a company
     *
     * @group Companies
     * @urlParam company int required The ID of the company. Example: 1
     * @response 200 {"id":1, "name":"Acme"}
     */
    public function show(Company $company)
    {
        return $this->success($company, 'Company retrieved');
    }

    /**
     * Return overview statistics for a single company.
     *
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @response 200 {
     *   "data": {"branches":1,"users":5,"patients":23}
     * }
     */
    public function stats(Company $company)
    {
        $branches = $company->branches()->count();
        $users = \App\Models\User::where('company_id', $company->id)->count();
        $patients = \App\Models\Patient::whereHas('branch', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->count();

        return $this->success(compact('branches', 'users', 'patients'), 'Company statistics');
    }

    /**
     * Update a company
     *
     * @group Companies
     * @urlParam company int required The ID of the company. Example: 1
     * @bodyParam name string Company name. Example: "Acme Updated"
     * @response 200 {"id":1, "name":"Acme Updated"}
     */
    public function update(\App\Http\Requests\UpdateCompanyRequest $request, Company $company)
    {
        $data = $request->validated();
        if ($request->has('send_notifications')) {
            $data['send_notifications'] = $request->boolean('send_notifications');
        }
        if ($request->has('notification_settings')) {
            $rawSettings = $request->input('notification_settings');
            $data['notification_settings'] = is_string($rawSettings)
                ? json_decode($rawSettings, true)
                : $rawSettings;
        }
        if ($request->has('visit_locations')) {
            $rawLocations = $request->input('visit_locations');
            $data['visit_locations'] = is_string($rawLocations)
                ? json_decode($rawLocations, true)
                : $rawLocations;
        }

        if ($request->hasFile('stamp')) {
            if ($company->stamp_path) {
                Storage::disk('local')->delete($company->stamp_path);
            }

            $path = $request->file('stamp')->store('signatures', 'local');
            $data['stamp_path'] = $path;
        }

        unset($data['stamp']);
        $company->update($data);

        return $this->success($company, 'Updated');
    }

    /**
     * List company subscriptions for management.
     */
    public function subscriptions(Request $request)
    {
        $query = Company::query()->with(['subscriptionTier'])->withCount('users');

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['name', 'ico', 'email'],
            allowedFilters: ['subscription_status', 'subscription_tier_id'],
            defaults: [
                'sort' => 'name',
            ]
        );

        return $this->success(new BaseCollection($results), 'Company subscriptions retrieved');
    }

    /**
     * Update subscription values for one company.
     */
    public function updateSubscription(Request $request, Company $company)
    {
        $validated = $request->validate([
            'subscription_tier_id' => ['nullable', 'integer', 'exists:subscription_tiers,id'],
            'subscription_price_monthly' => ['nullable', 'numeric', 'min:0'],
            'subscription_users_limit_override' => ['nullable', 'integer', 'min:1'],
            'subscription_status' => ['required', 'in:active,trial,paused,cancelled'],
            'subscription_notes' => ['nullable', 'string'],
            'paid_months_year' => ['required', 'integer', 'between:2020,2100'],
            'paid_months' => ['array'],
            'paid_months.*' => ['integer', 'between:1,12', 'distinct'],
            'payments' => ['array'],
            'payments.*.received_at' => ['required', 'date'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $company) {
            $company->update([
                'subscription_tier_id' => $validated['subscription_tier_id'] ?? null,
                'subscription_price_monthly' => $validated['subscription_price_monthly'] ?? null,
                'subscription_users_limit_override' => $validated['subscription_users_limit_override'] ?? null,
                'subscription_status' => $validated['subscription_status'],
                'subscription_notes' => $validated['subscription_notes'] ?? null,
                // Legacy fields are not used anymore in UI, keep them null to avoid conflicting logic.
                'subscription_started_at' => null,
                'subscription_ends_at' => null,
            ]);

            $year = (int) $validated['paid_months_year'];
            $months = collect($validated['paid_months'] ?? [])->map(fn($m) => (int) $m)->unique()->values();

            CompanySubscriptionPaidMonth::query()
                ->where('company_id', $company->id)
                ->where('year', $year)
                ->delete();

            if ($months->isNotEmpty()) {
                CompanySubscriptionPaidMonth::insert(
                    $months->map(fn($month) => [
                        'company_id' => $company->id,
                        'year' => $year,
                        'month' => $month,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }

            CompanySubscriptionPayment::query()->where('company_id', $company->id)->delete();

            $payments = collect($validated['payments'] ?? []);

            if ($payments->isNotEmpty()) {
                CompanySubscriptionPayment::insert(
                    $payments->map(fn($payment) => [
                        'company_id' => $company->id,
                        'received_at' => $payment['received_at'],
                        'amount' => $payment['amount'],
                        'notes' => $payment['notes'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }
        });

        return $this->success($this->subscriptionPayload($company->fresh()), 'Subscription updated');
    }

    public function subscriptionDetails(Company $company)
    {
        return $this->success($this->subscriptionPayload($company), 'Company subscription details retrieved');
    }

    private function subscriptionPayload(Company $company): array
    {
        $company->load([
            'subscriptionTier',
            'subscriptionPayments' => fn($q) => $q->orderByDesc('received_at')->orderByDesc('id'),
            'subscriptionPaidMonths' => fn($q) => $q->orderByDesc('year')->orderBy('month'),
        ]);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'subscription_tier_id' => $company->subscription_tier_id,
            'subscription_price_monthly' => $company->subscription_price_monthly,
            'subscription_users_limit_override' => $company->subscription_users_limit_override,
            'subscription_status' => $company->subscription_status,
            'subscription_notes' => $company->subscription_notes,
            'subscription_tier' => $company->subscriptionTier,
            'payments' => $company->subscriptionPayments->map(fn($payment) => [
                'id' => $payment->id,
                'received_at' => optional($payment->received_at)->toDateString(),
                'amount' => $payment->amount,
                'notes' => $payment->notes,
            ])->values(),
            'paid_months_by_year' => $company->subscriptionPaidMonths
                ->groupBy('year')
                ->map(fn($items, $year) => [
                    'year' => (int) $year,
                    'months' => $items->pluck('month')->map(fn($m) => (int) $m)->values(),
                ])
                ->values()
                ->sortByDesc('year')
                ->values(),
        ];
    }

    /**
     * Get the stamp image for a company
     *
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @response 200 {}
     */
    public function stamp(Company $company)
    {
        if (!$company->stamp_path || !Storage::disk('local')->exists($company->stamp_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($company->stamp_path, null, ['Content-Type' => 'image/png']);
    }

    /**
     * Delete the stamp for a company
     *
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @response 204 {}
     */
    public function deleteStamp(Company $company)
    {
        if ($company->stamp_path) {
            Storage::disk('local')->delete($company->stamp_path);
            $company->update(['stamp_path' => null]);
        }

        return $this->success(null, 'Stamp deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * List patients for a company
     *
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data": [{"id":1, "first_name":"John", "last_name":"Doe"}], "meta": {"total":1}}
     */
    public function patients(Request $request, Company $company)
    {
        $query = Patient::query()->whereHas('branch', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        if ($request->boolean('only_dead')) {
            $query->withTrashed()->whereNotNull('patients.death_date');
        } elseif ($request->boolean('only_deleted')) {
            $query->withTrashed()->whereNotNull('patients.deleted_at');
        } elseif ($request->boolean('dead_or_deleted')) {
            $query->withTrashed()->where(function ($q) {
                $q->whereNotNull('patients.deleted_at')
                    ->orWhereNotNull('patients.death_date');
            });
        } else {
            $query->whereNull('patients.death_date');
        }

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['first_name', 'last_name', 'personal_number'],
            allowedFilters: ['sex', 'nurse_id'],
            defaults: ['sort' => 'last_name']
        );
        return $this->success(new BaseCollection(resource: $results), 'Patients retrieved');
    }

    /**
     * List users for a company
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data": [{"id":1, "first_name":"John", "last_name":"Doe"}], "meta": {"total":1}}
     */
    public function users(Request $request, Company $company)
    {
        if (!$company) {
            return $this->success(new UserCollection(collect([])), 'Users retrieved');
        }

        $query = User::query()->whereHas('company', function ($q) use ($company) {
            $q->where('company.id', $company->id);
        });
        $results = ApiQuery::apply($request, $query);
        return $this->success(new UserCollection($results), 'Users retrieved');
    }
    /**
     * List branches for a company
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data": [{"id":1, "name":"Main Branch
     *"}], "meta": {"total":1}}
     */
    public function branches(Request $request, Company $company)
    {
        $companyId = $company->id ?? null;
        if (!$companyId) {
            return $this->success(new BaseCollection(collect([])), 'Company branches retrieved');
        }

        $query = Branch::query()->where('company_id', $companyId);
        $results = ApiQuery::apply($request, $query);
        return $this->success(new BaseCollection($results), 'Company branches retrieved');
    }


    /**
     * Delete a company
     *
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @response 204 {}
     */
    public function destroy(Company $company)
    {
        DB::transaction(function () use ($company) {
            User::where('company_id', $company->id)->delete();
            $company->delete();
        });

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * Bulk delete companies
     *
     * @group Companies
     * @bodyParam ids array required Array of company IDs to delete. Example: [1,2,3]
     * @response 204 {}
     */
    public function destroyMany(DestroyManyCompanyRequest $request)
    {
        $ids = $request->input('ids', []);

        DB::transaction(function () use ($ids) {
            User::whereIn('company_id', $ids)->delete();
            Company::whereIn('id', $ids)->delete();
        });

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }


}
