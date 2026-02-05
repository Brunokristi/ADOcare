# Database Schema Recommendations — Short Memo

To: Database/Platform Team

Subject: Issues found in the current schema and recommended fixes

Purpose: This short memo outlines practical problems observed in the schema and specific recommendations to address them. Each item is structured as "Problem" followed by a concise recommendation and a suggested migration approach.

### Problem: Global vs branch-scoped roles are mixed
Recommendation:
- Problem: Currently global roles and branch-scoped roles are stored in overlapping places, which complicates permission checks and increases risk of inconsistent role resolution.
- Recommendation: Keep *global roles* (e.g., `manager`, `admin`) on the `users` table (a `role_id` or `role` enum), and move *branch-scoped roles* (e.g., `nurse`, `assistant`) to the `user_branches` pivot (`branch_role_id` or an enum referencing a small lookup table). This clean separation makes authorization logic simpler and safer.
- Suggested migration: Add a nullable `branch_role_id` on `user_branches`, run a deterministic backfill (map existing assignments), update services/controllers to consult the pivot for branch-level checks, and remove legacy fields after verification on staging and production.

---

### Problem: Address fields are inconsistent and ambiguous
Recommendation:
- Problem: The schema mixes languages and field meanings (e.g., `psc` is Slovak, `address` is used for street+number while `city` and other fields are English). This makes validation, UX, and internationalization harder.
- Recommendation (short-term): Rename `psc` → `zip`, add a `street` column for street+number, and keep `address` as the formatted full address for display and redundancy.
- Recommendation (long-term): Create a normalized `addresses` table (street, city, zip, country, latitude, longitude, formatted) and reference it from `companies`, `branches`, `patients`. This centralizes validation and geocoding and simplifies future changes.
- Suggested migration: Add new columns or the `addresses` table, backfill with best-effort parsing, update application code to use the new schema, and only drop legacy columns after validation and monitoring.

---

### Problem: Raw SQL and ad-hoc DB access are scattered in code
Recommendation:
- Problem: There are many ad-hoc raw queries and `DB::` usage across services, controllers and jobs which increases maintenance burden and risk (SQL injection, schema drift, harder to test).
- Recommendation: Prefer Eloquent relations and the Query Builder for normal operations. Reserve raw SQL for cases with clear, documented performance or functional justification.
- Suggested practice: If raw SQL is used, document the reason in the PR, use parameter binding, add tests, and consider wrapping the logic in a service method for reuse and testability.

---

### Problem: Large updates and migrations can cause outages or OOM
Recommendation:
- Problem: Backfills and large updates may lock tables or use excessive memory.
- Recommendation: Use `chunk()` or `cursor()` to process large datasets and run backfills on staging first. Use transactions for multi-step changes where appropriate and include monitoring/alerts for long-running jobs.

---

### Problem: Missing constraints / inconsistent types
Recommendation:
- Problem: Some tables lack explicit constraints and consistent column types, which can lead to subtle bugs and performance issues.
- Recommendation: Add foreign keys and indexes on frequently queried columns, define explicit types and constraints (e.g., VARCHAR lengths, `CHECK` for enums, `DECIMAL` for money), and consider `CITEXT` where case-insensitive comparison is desired.

---

### Problem: Lack of auditing and soft-deletes where useful
Recommendation:
- Problem: Key actions and deletions are not always auditable and some entities would benefit from recoverability.
- Recommendation: Add `created_by` / `updated_by` audit columns for important tables and use soft deletes (`deleted_at`) for resources that may be recovered or referenced historically.

---

Migration best-practices
- Make changes non-destructive: add new columns / tables first, perform deterministic backfills, update application code to use new data, then remove old columns after a verified monitoring window.
- For complex backfills, include a rollback plan, test on staging, and add automated tests where practical.

If you'd like, I can draft example migrations and backfill scripts for any of the above changes — tell me which one to start with.