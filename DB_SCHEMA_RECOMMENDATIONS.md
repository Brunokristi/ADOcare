# Database Schema Recommendations — Short Memo

### Global vs branch-scoped roles are mixed
- Problem: Currently global roles and branch-scoped roles are stored in overlapping places, which complicates permission checks and increases risk of inconsistent role resolution.

- Recommendation: Keep *global roles* (e.g., `manager`, `admin`) on the `users` table (a `role_id` or `role` enum), and move *branch-scoped roles* (e.g., `nurse`, `assistant`) to the `user_branches` pivot (`branch_role_id` or an enum referencing a small lookup table). This clean separation makes authorization logic simpler and safer.
- Suggested migration: Add a nullable `branch_role_id` on `user_branches`, run a deterministic backfill (map existing assignments), update services/controllers to consult the pivot for branch-level checks, and remove legacy fields after verification on staging and production.

---

### Address fields are inconsistent and ambiguous
- Problem: The schema mixes languages and field meanings (e.g., `psc` is Slovak, `address` is used for street+number while `city` and other fields are English). This makes validation, UX, and internationalization harder.

- Recommendation (short-term): Rename `psc` → `zip`, add a `street` column for street+number, and keep `address` as the formatted full address for display and redundancy.
- Recommendation (long-term): Create a normalized `addresses` table (street, city, zip, country, latitude, longitude, formatted) and reference it from `companies`, `branches`, `patients`. This centralizes validation and geocoding and simplifies future changes.
- Suggested migration: Add new columns or the `addresses` table, backfill with best-effort parsing, update application code to use the new schema, and only drop legacy columns after validation and monitoring.

---

### Missing constraints / inconsistent types
- Problem: Some tables lack explicit constraints and consistent column types, which can lead to subtle bugs and performance issues.

- Recommendation: Add foreign keys and indexes on frequently queried columns, define explicit types and constraints (e.g., VARCHAR lengths, `CHECK` for enums, `DECIMAL` for money), and consider `CITEXT` where case-insensitive comparison is desired.

---

### Lack of auditing and soft-deletes where useful
- Problem: Key actions and deletions are not always auditable and some entities would benefit from recoverability.

- Recommendation: Add `created_by` / `updated_by` audit columns for important tables and use soft deletes (`deleted_at`) for resources that may be recovered or referenced historically.

---
