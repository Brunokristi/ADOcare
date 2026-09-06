<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\CarCollection;
use App\Http\Resources\DoctorCollection;
use App\Http\Resources\UserCollection;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CompanySubscriptionPayment;
use App\Models\Doctor;
use App\Models\User;
use App\Services\CompanyDeletionService;
use App\Services\StudioKristianBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use \App\Http\Controllers\Controller;

class MyCompanyController extends Controller
{
    public function __construct(private StudioKristianBillingService $billing)
    {
    }

    /**
     * Get current user's company
     *
     * @group Companies
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return $this->success(null, 'No company associated with current user');
        }

        return $this->success($company->load(['representative']), 'My company retrieved');
    }

    /**
     * Update current user's company.
     */
    public function update(UpdateCompanyRequest $request)
    {
        $user = $request->user();
        $company = $user?->company;

        if (!$company) {
            return $this->notFound('No company associated with current user');
        }

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

        // Best-effort - a StudioKristian outage must never block saving Company settings.
        // Keeps the StudioKristian/Stripe billing customer identity in sync with the real
        // Company (name/address/ICO/DIC/...) whenever it changes here, not only at onboarding.
        $this->billing->syncBillingProfile($company);

        return $this->success($company, 'Updated', Response::HTTP_OK);
    }

    /**
     * Permanently deactivate the current user's Company: soft-deletes the Company, its
     * Users, and its Patients (recoverable by a superadmin), and removes everything else
     * that only makes sense in the context of an active Company (branches, cars, documents,
     * invoices, points, visits, report months, per-company procedure prices, legacy
     * subscription records). Every affected User's API tokens are revoked, so the requesting
     * user (and everyone else at the Company) is signed out on their very next request.
     */
    public function destroy(Request $request, CompanyDeletionService $deletions)
    {
        $user = $request->user();
        $company = $user?->company;

        if (!$company) {
            return $this->notFound('No company associated with current user');
        }

        $request->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if (trim((string) $request->input('confirm_name')) !== $company->name) {
            return $this->error('Zadaný názov spoločnosti sa nezhoduje s aktuálnym názvom.', 422);
        }

        $deletions->delete($company);

        return $this->success(null, 'Company deleted', Response::HTTP_OK);
    }

    /**
     * List branches for current user's company
     *
     * @group Companies
     */
    public function branches(Request $request)
    {
        $user = $request->user();

        $companyId = $user->company->id ?? null;
        if (!$companyId) {
            return $this->success(new BaseCollection(collect([])), 'Company branches retrieved');
        }

        $query = Branch::query()->where('company_id', $companyId);
        $results = ApiQuery::apply($request, $query);
        return $this->success(new BaseCollection($results), 'Company branches retrieved');
    }

    /**
     * List cars for the user's company
     *
     * @group Cars
     */
    public function cars(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return $this->success(new CarCollection(collect([])), 'Cars retrieved');
        }

        $query = Car::query()->where('company_id', $company->id);
        $results = ApiQuery::apply(request(), $query, searchable: ['evc', 'model', 'user' => ['first_name', 'last_name']], allowedFilters: ['user_id']);
        return $this->success(new CarCollection($results), 'Cars retrieved');
    }

    /**
     * List users for the current user's company
     *
     * @group Users
     */
    public function users(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return $this->success(new UserCollection(collect([])), 'Users retrieved');
        }

        $query = User::query()->whereHas('company', function ($q) use ($company) {
            $q->where('company.id', $company->id);
        });
        $results = ApiQuery::apply($request, $query);
        return $this->success(new UserCollection($results), 'Users retrieved');
    }

    public function doctors()
    {
        $user = request()->user();
        $company = $user->company;

        if (!$company) {
            return $this->success(new DoctorCollection(collect([])), 'Doctors retrieved');
        }

        $query = Doctor::query()->whereHas('assigned_branches', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        $results = ApiQuery::apply(request(), $query, searchable: ['first_name', 'last_name']);

        return $this->success(new DoctorCollection($results), 'Doctors retrieved');
    }

    /**
     * Get current user's company subscription details.
     */
    public function subscriptionDetails(Request $request)
    {
        $company = $request->user()?->company;

        if (!$company) {
            return $this->notFound('No company associated with current user');
        }

        $company->load([
            'subscriptionTier',
            'subscriptionPayments' => fn($q) => $q->orderByDesc('received_at')->orderByDesc('id'),
            'subscriptionPaidMonths' => fn($q) => $q->orderByDesc('year')->orderBy('month'),
        ]);

        return $this->success([
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
        ], 'My company subscription details retrieved');
    }

    /**
     * List current user's company subscription payments.
     */
    public function subscriptionPayments(Request $request)
    {
        $company = $request->user()?->company;

        if (!$company) {
            return $this->success(new BaseCollection(collect([])), 'My company subscription payments retrieved');
        }

        $query = CompanySubscriptionPayment::query()
            ->where('company_id', $company->id)
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['notes'],
            allowedFilters: ['received_at']
        );

        return $this->success(new BaseCollection($results), 'My company subscription payments retrieved');
    }

}
