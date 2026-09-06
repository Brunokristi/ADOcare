<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StudioKristianBillingException;
use App\Http\Controllers\Controller;
use App\Services\StudioKristianBillingService;
use App\Support\CompanySubscription;
use Illuminate\Http\Request;

/**
 * Consumer-facing billing endpoints backed by the StudioKristian Billing API.
 * ADOCare never talks to Stripe directly - this controller only ever
 * delegates to StudioKristianBillingService.
 */
class BillingController extends Controller
{
    public function __construct(private StudioKristianBillingService $billing)
    {
    }

    /**
     * List the plans/prices available for the ADOCare SaaS Project.
     */
    public function plans()
    {
        try {
            return $this->success($this->billing->getPlans(), 'Plans retrieved');
        } catch (StudioKristianBillingException $e) {
            return $this->error($e->getMessage(), $e->status());
        }
    }

    /**
     * Current billing/subscription state for the authenticated user's Company: the
     * application-managed trial, StudioKristian's paid subscriptions, payments and invoices,
     * plus the resolved effective/current billing state (paid subscription takes precedence
     * over the trial). Fetched in a single request to StudioKristian - subscriptions, trial,
     * payments and invoices are all bundled in the one `/customer/subscriptions` response.
     */
    public function subscription(Request $request)
    {
        $company = $request->user()?->company;

        if (!$company) {
            return $this->notFound('Ku aktuálnemu používateľovi nie je priradená žiadna spoločnosť');
        }

        $payload = [
            'trial' => CompanySubscription::localTrialState($company),
            'billing_provisioned' => $company->hasBillingCustomerToken(),
            'subscriptions' => [],
            'payments' => [],
            'invoices' => [],
        ];

        if ($company->hasBillingCustomerToken()) {
            try {
                $snapshot = $this->billing->getCustomerBillingSnapshot($company);
            } catch (StudioKristianBillingException $e) {
                return $this->error($e->getMessage(), $e->status());
            }

            $payload['trial'] = CompanySubscription::trialStateFromSnapshot($company, $snapshot['trial']);
            $payload['subscriptions'] = $snapshot['subscriptions'];
            $payload['payments'] = $snapshot['payments'];
            $payload['invoices'] = $snapshot['invoices'];
        }

        $payload['current'] = CompanySubscription::resolveCurrentState($payload['trial'], $payload['subscriptions']);

        // A fresh, direct read just proved the real state - never let the (separately cached)
        // access-control view linger stale relative to what we just confirmed, e.g. right
        // after a checkout StudioKristian has already synced but the 60s cache hasn't expired.
        if ($payload['current']['type'] === 'subscription') {
            CompanySubscription::forgetRemoteCache($company);
        }

        return $this->success($payload, 'Billing state retrieved');
    }

    /**
     * Start a Stripe Checkout session via StudioKristian for the chosen plan price.
     */
    public function checkout(Request $request)
    {
        $company = $request->user()?->company;

        if (!$company) {
            return $this->notFound('Ku aktuálnemu používateľovi nie je priradená žiadna spoločnosť');
        }

        $validated = $request->validate([
            'plan_price_id' => ['required', 'integer'],
            'success_url' => ['required', 'string'],
            'cancel_url' => ['required', 'string'],
        ]);

        if (!$company->hasBillingCustomerToken()) {
            return $this->error('Táto spoločnosť ešte nemá priradené fakturačné údaje StudioKristian.', 422);
        }

        try {
            if (!$this->billing->isValidPlanPriceId((int) $validated['plan_price_id'])) {
                return $this->error('Zvolená cena balíka nie je platná.', 422);
            }

            $session = $this->billing->createCheckoutSession(
                $company,
                (int) $validated['plan_price_id'],
                $validated['success_url'],
                $validated['cancel_url'],
            );
        } catch (StudioKristianBillingException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success($session, 'Checkout session created');
    }
}
