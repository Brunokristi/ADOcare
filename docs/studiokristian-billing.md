# StudioKristian Billing Integration

ADOCare does not own billing. StudioKristian is the SaaS billing platform and the
source of truth for plans, prices, Stripe objects, subscriptions, invoices and
billing state. ADOCare is only a customer-facing consumer of StudioKristian's
Billing API.

```
ADOCare frontend -> ADOCare Laravel backend -> StudioKristian Billing API -> Stripe
```

Mental model used throughout the code:

- **Project Token** = "I am ADOCare." Identifies the ADOCare SaaS Project. Server-side only.
- **Customer Token** = "I am Company X." Identifies one Company within the ADOCare SaaS Project. Server-side only, stored encrypted on `company.studiokristian_customer_token`.
- **plan_price_id** = "I want this StudioKristian plan price." Internal StudioKristian id - never a Stripe Price ID.
- **Stripe** = payment infrastructure, fully owned and operated by StudioKristian (including webhooks).

## Environment variables

Server-side only, never exposed to the frontend:

| Variable | Required | Description |
|---|---|---|
| `STUDIOKRISTIAN_BILLING_URL` | yes | Base URL of the StudioKristian Billing API. |
| `STUDIOKRISTIAN_BILLING_PROJECT_TOKEN` | yes | Bearer token identifying the ADOCare SaaS Project. |
| `STUDIOKRISTIAN_BILLING_TIMEOUT` | no (default 10s) | HTTP timeout for StudioKristian requests. |
| `STUDIOKRISTIAN_BILLING_PLANS_CACHE_TTL` | no (default 300s) | How long `/billing/plans` is cached. |

No Stripe secret keys are required or used by ADOCare.

## Backend components

- `App\Services\StudioKristianBillingService` - the only place allowed to call StudioKristian. Always sends `Authorization: Bearer {PROJECT_TOKEN}`; adds `X-Billing-Customer-Token` for customer-scoped calls; generates a deterministic `Idempotency-Key` per (company, plan_price_id) for checkout, cached for 5 minutes so retries don't create duplicate Checkout Sessions. Also exposes `startTrial()` / `getTrialState()` (`POST`/`GET /api/v1/billing/customer/trial`, empty body - StudioKristian resolves the Company/Project from the credentials and owns trial duration/credits).
- `App\Http\Controllers\Api\BillingController` - thin controller exposing `GET v1/billing/plans`, `GET v1/billing/subscription`, `POST v1/billing/checkout`. The Company is always derived from the authenticated user - never from a `company_id` supplied by the browser.
- `App\Http\Controllers\Api\OnboardingController` - `GET v1/onboarding/status`, `POST v1/onboarding/billing/provision`, `POST v1/onboarding/billing/start-trial`, `POST v1/onboarding/complete`. `start-trial` requires a Customer Credential first and calls StudioKristian for real - never a Stripe trial, never fabricated locally.
- `App\Support\CompanySubscription` - once a Company has a Customer Credential, StudioKristian is authoritative for BOTH paid subscription state (`hasActiveSubscription()`, cached 60s) and trial state (`trialState()`, cached 30s). Both fail open to local cached fields (`subscription_status`/`subscription_ends_at`) if StudioKristian is unavailable. `forgetRemoteCache()` must be called right after any action that changes StudioKristian's state for a Company (e.g. after starting a trial) to avoid serving stale cached data in the same request/response cycle.
- **Access states**: `EnsureCompanySubscriptionActive` middleware allows a request through if the Company is `status === 'onboarding'` (no billing required yet), OR has an active trial, OR has an active paid subscription. Otherwise it returns 402. This is the only place 402 is produced - billing API outages/misconfiguration surface as their own status codes instead.
- `App\Console\Commands\ProvisionStudioKristianCustomerCredential` (`php artisan billing:provision-customer {company_id}`) - calls StudioKristian's self-service `POST /api/v1/billing/customer-credentials` endpoint (Project Credential only, no StudioKristian admin session) and stores the returned token on the Company. Manual/ops tool; the onboarding flow's `POST v1/onboarding/billing/provision` does the same thing for the authenticated Company automatically, and is idempotent (a no-op if the Company already has a token).
- **Provisioning contract**: ADOCare sends `{ external_reference: (string) $company->id, name }` - `external_reference` is ADOCare's own opaque Company id, never a StudioKristian id. StudioKristian keys the credential by `(project, external_reference)` and creates a StudioKristian-side Company to scope subscriptions/trials to on first use; repeating the call returns `already_provisioned: true` without minting a second credential. If StudioKristian reports `already_provisioned: true` with no token (the plaintext token was lost locally and cannot be re-issued), ADOCare raises a clear 409 rather than silently pretending success.

## Security invariants

- `plan_price_id` sent by the frontend is validated against the current `/billing/plans` catalog before Checkout is created - a foreign/Stripe id is rejected with 422 and StudioKristian is never called.
- Neither the Project Token nor any Company's Customer Token is ever serialized in an API response (`Company::$hidden`).
- The frontend cannot select which Company's billing state it sees - it's always the authenticated user's Company.
- A successful redirect from Stripe Checkout is **not** treated as proof of payment. `/billing/success` polls `GET v1/billing/subscription` (StudioKristian) a few times before showing a final state.

## Local testing

1. Set `STUDIOKRISTIAN_BILLING_URL` and `STUDIOKRISTIAN_BILLING_PROJECT_TOKEN` in `.env` to point at a StudioKristian sandbox project.
2. Provision (or reuse) a Customer Credential for a test Company:
   ```
   php artisan billing:provision-customer {company_id}
   ```
   or attach an existing token directly on the `company.studiokristian_customer_token` column.
3. Log into ADOCare as a user of that Company and open the Billing page - plans should load from StudioKristian.
4. Pick a plan/price and start Checkout; ADOCare redirects the browser to the Stripe Checkout URL returned by StudioKristian.
5. Complete a Stripe test-mode payment.
6. Stripe sends its webhook to **StudioKristian** (not ADOCare):
   ```
   stripe listen --forward-to <studiokristian-host>/api/webhooks/stripe
   ```
7. Return to ADOCare's `/billing/success` page - it polls StudioKristian until the subscription shows as `active`/`trialing`.

## Automated tests

See `tests/Feature/StudioKristianBillingTest.php` for coverage of: plans retrieval, customer/project credential headers, internal `plan_price_id` validation, idempotency key stability, missing-credential/misconfiguration handling, StudioKristian outage handling, checkout-does-not-imply-active-subscription, credential provisioning, and removal of Superadmin's manual subscription write endpoints.
