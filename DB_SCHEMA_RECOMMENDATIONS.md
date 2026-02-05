# Database Schema Recommendations (problem → recommendation)

This short note restates recommended schema changes and best practices using a clear Problem / Recommendation structure to make proposals easier to review and act on.

### Problem: Global vs branch-scoped roles are mixed
Recommendation:
- Keep global roles (e.g., `manager`, `admin`) referenced directly on `users` (e.g., `role_id` or `role` enum column).
- Keep branch-scoped roles (e.g., `nurse`, `assistant`) on the `user_branches` pivot as `role_id` (or a small lookup/enum) so permissions are scoped and checks are unambiguous.
Migration approach: add a nullable `branch_role_id` to `user_branches`, backfill from existing assignments deterministically, update services/controllers to consult the pivot for branch-scoped permissions, then optionally remove old combined fields after verification.

---

### Problem: Address fields are inconsistent and ambiguous
Recommendation:
- Minimal: rename `psc` → `zip`, add `street` (street + number), and keep `address` as a full-formatted address for display and redundancy.
- Normalized: create an `addresses` table with fields (street, city, zip, country, latitude, longitude, formatted) and reference it from entities (companies, branches, patients). This centralizes validation, geocoding and reduces duplication.
Migration approach: add new fields/table and backfill using best-effort parsing, update read/write code paths to use new columns/relations, and only drop legacy columns once verified in staging and production monitoring.

---

### Problem: Raw SQL and ad-hoc DB access are scattered in code
Recommendation:
- Prefer Eloquent relations and Query Builder. Only use raw SQL (`DB::raw`, `DB::statement`) when there's an unavoidable need (performance-critical aggregation, vendor-specific SQL).
- When raw SQL is used, justify it in the PR, use parameter binding, and add tests that cover the edge cases.

---

### Problem: Large updates and migrations can cause outages or OOM
Recommendation:
- Use `chunk()` or `cursor()` for large dataset processing and wrap multi-step changes in transactions where possible. Test backfills on staging and monitor long-running jobs.

---

### Problem: Missing constraints / inconsistent types
Recommendation:
- Add foreign keys and indexes for frequently queried columns. Use explicit column types and constraints (VARCHAR lengths, `CHECK` for enums/zip formats). Consider `CITEXT` for case-insensitive text if appropriate.

---

### Problem: Lack of auditing and soft-deletes where useful
Recommendation:
- Add `created_by`, `updated_by` audit columns for key resources and prefer soft deletes (`deleted_at`) where resources are recoverable or referenced historically.

---

Migration best-practices
- Make changes non-destructive: add new columns / tables first, perform deterministic backfills, update application code to use new data, then remove old columns after a verified monitoring window.
- For complex backfills, include a rollback plan, test on staging, and add automated tests where practical.

If you'd like, I can draft example migrations and backfill scripts for any of the above changes — tell me which one to start with.