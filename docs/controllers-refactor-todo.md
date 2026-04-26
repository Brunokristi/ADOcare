# Controllers Refactor TODO

Generated: 2026-04-26

Summary:
- Based on a code-quality review of all controllers in `app/Http/Controllers`.
- This document lists high-priority controllers to refactor, their severity,
  estimated difficulty, and a concise action item. Use the checkboxes to
  track progress.

Refactor priority tasks (ordered)

## Critical

- [x] [app/Http/Controllers/Api/AuthController.php](app/Http/Controllers/Api/AuthController.php) — Severity: Critical — Difficulty: Easy — Action: Fix namespace and `createToken()` default expiry handling so authentication endpoints work; apply quick patch and add tests.

## Major

- [ ] [app/Http/Controllers/Api/PointsExportController.php](app/Http/Controllers/Api/PointsExportController.php) — Severity: Major — Difficulty: Hard — Action: Extract SQL, aggregation and file-format logic into a dedicated `PointsExportService`; add FormRequest and unit tests.

- [ ] [app/Http/Controllers/Api/KilometersExportController.php](app/Http/Controllers/Api/KilometersExportController.php) — Severity: Major — Difficulty: Hard — Action: Move heavy logic and external route-service calls into `KilometersExportService`; add retry/circuit-breaker and tests.

- [ ] [app/Http/Controllers/Api/ManagerController.php](app/Http/Controllers/Api/ManagerController.php) — Severity: Major — Difficulty: Hard — Action: Move raw SQL and pivoting to repository/service layer; add caching for expensive queries and unit tests.

- [ ] [app/Http/Controllers/Api/InvoiceController.php](app/Http/Controllers/Api/InvoiceController.php) — Severity: Major — Difficulty: Medium — Action: Extract transactional invoice creation/update logic into `InvoiceService` and add integration tests for concurrency.

- [ ] [app/Http/Controllers/Api/TotalsController.php](app/Http/Controllers/Api/TotalsController.php) — Severity: Major — Difficulty: Medium — Action: Move aggregation logic to `TotalsService` and optimize DB queries.

- [ ] [app/Http/Controllers/Api/DocumentController.php](app/Http/Controllers/Api/DocumentController.php) — Severity: Major — Difficulty: Medium — Action: Delegate PDF/HTML generation and storage operations to `DocumentService`; thin controller logic.

- [ ] [app/Http/Controllers/Api/BatchDocumentController.php](app/Http/Controllers/Api/BatchDocumentController.php) — Severity: Major — Difficulty: Medium — Action: Extract shared sorting/pagination and payload parsing into shared utilities or services.

- [ ] [app/Http/Controllers/Api/PointsBatchDocumentController.php](app/Http/Controllers/Api/PointsBatchDocumentController.php) — Severity: Major — Difficulty: Medium — Action: Consolidate duplication with `KilometersBatchDocumentController` and extract shared helpers.

- [ ] [app/Http/Controllers/Api/KilometersBatchDocumentController.php](app/Http/Controllers/Api/KilometersBatchDocumentController.php) — Severity: Major — Difficulty: Medium — Action: Consolidate duplication with `PointsBatchDocumentController` and extract shared helpers.

## Minor / Low priority (cleanup & consistency)

- [x] [app/Http/Controllers/Api/PatientController.php](app/Http/Controllers/Api/PatientController.php) — Severity: Minor — Difficulty: Medium — Action: Move some heavy operations into `PatientService` (already partially done); add tests.

- [x] [app/Http/Controllers/Api/ScanUploadController.php](app/Http/Controllers/Api/ScanUploadController.php) — Severity: Minor — Difficulty: Medium — Action: Consider moving `normalizeToJpegIfNeeded()` into service/utility; add unit tests for conversions.

## Suggested next steps

1. Apply the quick AuthController fix (Critical) first so auth endpoints are functional.
2. Add CI checks and basic unit tests for auth and one export endpoint.
3. Tackle one Major controller at a time: extract service, add tests, then mark the checkbox.
