# Database Schema Recommendations (short)

This short note lists recommended schema changes and best practices to improve clarity, consistency and future extensibility.

## 1) Roles: separate global vs branch-scoped
- Keep *global roles* (e.g., `manager`, `admin`) referenced directly on `users` (e.g., `role_id`).
- Keep *branch-scoped roles* (e.g., `nurse`, `assistant`) on the pivot `user_branches` table as `role_id` (or a small enum/lookup). This avoids mixing scopes and makes permission checks clearer.

Migration plan: add new pivot column, backfill deterministically, and update authorization logic in services/controllers.

## 2) Address normalization
- Current fields are inconsistent (`address` used for street+number, `city`, `psc`). Recommended options:
  1. Minimal change: rename `psc` → `zip`, add `street` (street + number), and keep `address` as full formatted address for redundancy and display.
  2. Stronger normalization: create an `addresses` table (street, city, zip, lat, lon, country, formatted) and reference it from `companies`, `branches`, `patients`, etc. This centralizes validation, geocoding, and future changes.

Migration plan: add new `street` (or `addresses` table), backfill from existing data (best-effort parsing), update reads/writes, and remove old columns only after verification.

## 3) Other suggestions
- Prefer Eloquent relations and Query Builder over raw SQL. Document and justify raw queries when necessary.
- Add foreign keys and indexes for frequently queried columns (FK with `ON DELETE SET NULL` where appropriate).
- Use explicit column types and constraints (e.g., VARCHAR lengths, `CHECK` for enums/zip formats, `DECIMAL` for money) and consider `CITEXT` for case-insensitive strings.
- For large migrations or backfills, use chunked updates and run on staging with monitoring; avoid long table locks.
- Add audit fields where useful (`created_by`, `updated_by`) and consider soft deletes where appropriate.

## 4) Migration best-practices
- Make migrations non-destructive: add columns first, backfill deterministically, switch code to use new columns, then remove old columns.
- Include tests where possible, and document the rollout steps in the PR description.

If you want, I can draft example migrations and backfill scripts for any of the above changes — tell me which one to start with.