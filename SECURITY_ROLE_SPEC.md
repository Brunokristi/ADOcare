# Security Role Specification (Current Implementation)

This document describes the practical authorization behavior currently implemented in the codebase.

Scope:
- API authorization and route middleware
- Policy-based model access
- Frontend route-level role restrictions

Date baseline: 2026-03-22

## 1. Role Model

Defined roles:
- `superadmin` (global)
- `manager` (company)
- `nurse` (branch)

Source: `database/seeders/Static/RoleSeeder.php`

Practical fallback behavior:
- If user has no global role assigned, backend role middleware treats user as `nurse`.
- Source: `app/Http/Middleware/EnsureUserHasRole.php`

## 2. Authorization Layers

1. Authentication:
- `api.auth` middleware requires Sanctum-authenticated user.
- Source: `app/Http/Middleware/EnsureApiAuthenticated.php`

2. Coarse role checks:
- `role:*` middleware checks global role (`role:any` allows any authenticated role).
- Source: `app/Http/Middleware/EnsureUserHasRole.php`

3. Fine-grained model checks:
- `can:view,patient` / `can:view,document` use registered policies.
- Policy registration source: `app/Providers/AuthServiceProvider.php`

4. Request-level scope checks:
- Some endpoints enforce branch/company ownership in FormRequest `authorize()` methods.

5. Frontend navigation restrictions:
- Vue router enforces `meta.roles` in navigation guard.
- Sources: `resources/js/router/index.ts`, `resources/js/router/manager.ts`, `resources/js/router/superadmin.ts`

Important:
- Frontend route guards are UX-level only. Backend API rules are the security boundary.

## 3. Practical Capability Matrix by Domain

Legend:
- View = list/read
- Mutate = create/update/delete/bulk delete
- Scope = data boundary expected by policies/queries

### 3.1 Global reference tables

| Domain / table           | Nurse | Manager | Superadmin    | Enforcement                                               |
| ------------------------ | ----- | ------- | ------------- | --------------------------------------------------------- |
| Insurance companies      | View  | View    | View + Mutate | Routes split read `role:any`, write `role:superadmin`     |
| Doctors (global catalog) | View  | View    | View + Mutate | Routes split read `role:any`, write `role:superadmin`     |
| Diagnoses                | View  | View    | View + Mutate | Routes split read `role:any`, write `role:superadmin`     |
| Procedures               | View  | View    | View + Mutate | `apiResourceComplete(..., 'role:superadmin', 'role:any')` |

Source: `routes/api.php`

### 3.2 Company administration

| Domain                                       | Nurse | Manager                | Superadmin | Enforcement                                            |
| -------------------------------------------- | ----- | ---------------------- | ---------- | ------------------------------------------------------ |
| Companies CRUD                               | No    | No                     | Yes        | `role:superadmin`                                      |
| Company stats/users/branches/stamp endpoints | No    | Yes (company expected) | Yes        | `role:manager,superadmin` + controller/service scoping |
| Branches CRUD                                | No    | Yes                    | Yes        | `role:manager,superadmin`                              |
| Users CRUD / signatures / branch assignment  | No    | Yes                    | Yes        | `role:manager,superadmin`                              |
| Manager reporting endpoints                  | No    | Yes                    | Yes        | `role:manager,superadmin`                              |

Source: `routes/api.php`

### 3.3 Patient and branch operations

| Domain                                                               | Nurse                         | Manager            | Superadmin | Enforcement                                                     |
| -------------------------------------------------------------------- | ----------------------------- | ------------------ | ---------- | --------------------------------------------------------------- |
| Patients resource (except index/store in current route registration) | Yes                           | Yes                | Yes        | `role:any` + patient policy where `can:view,patient` is applied |
| Patient nested endpoints (`/patients/{patient}/...`)                 | Yes (assigned patient/branch) | Yes (same company) | Yes        | `role:any` + `can:view,patient`                                 |
| Branch patient listing/assignments and favourite doctors read        | Yes                           | Yes                | Yes        | `role:any`                                                      |
| Branch favourite doctor attach/detach                                | No                            | Yes                | Yes        | `role:manager,superadmin`                                       |

Policy scope source: `app/Policies/PatientPolicy.php`

### 3.4 Clinical documents and batches

| Domain                                                 | Nurse | Manager | Superadmin | Enforcement                                                              |
| ------------------------------------------------------ | ----- | ------- | ---------- | ------------------------------------------------------------------------ |
| Proposals/Agreements/CP/DZC/Dekurz/Leave/Record routes | Yes   | Yes     | Yes        | `role:any` + `can:view,patient/document` on read routes where configured |
| Kilometers batches / points batches                    | Yes   | Yes     | Yes        | `role:any` + `can:view,document` on detail routes                        |
| Batch company aggregations                             | No    | Yes     | Yes        | `role:manager,superadmin`                                                |

Policy scope source: `app/Policies/DocumentPolicy.php`

### 3.5 Other domains

| Domain                                 | Nurse | Manager | Superadmin | Enforcement                               |
| -------------------------------------- | ----- | ------- | ---------- | ----------------------------------------- |
| Roles CRUD and role metadata endpoints | No    | No      | Yes        | `role:superadmin`                         |
| Totals endpoints                       | No    | Yes     | Yes        | `role:manager,superadmin`                 |
| Visits endpoints                       | Yes   | Yes     | Yes        | `role:any`                                |
| Cities/Countries/Geocode               | Yes   | Yes     | Yes        | inherited `api.auth` (no extra role gate) |

## 4. Policy Rules (Model-Level)

### PatientPolicy (`app/Policies/PatientPolicy.php`)
- `view`:
  - manager/admin-like role in same company -> allowed
  - nurse in branch and assigned to patient (`patient.nurse_id === user.id`) -> allowed
- `update`:
  - manager/admin-like same company OR assigned nurse
- `delete`:
  - manager/admin-like same company

### DocumentPolicy (`app/Policies/DocumentPolicy.php`)
- `view/update/delete`:
  - document owner -> allowed
  - manager/admin-like in same company as document patient/branch -> allowed

## 5. Frontend Practical Behavior

Frontend route families:
- Nurse/general routes: `resources/js/router/nurse.ts`
- Manager routes: `resources/js/router/manager.ts` (meta role `manager`)
- Superadmin routes: `resources/js/router/superadmin.ts` (meta role `superadmin`)

Router guard behavior:
- Requires auth by default unless `meta.requiresAuth === false`.
- If `meta.roles` exists, current role must be included.
- Source: `resources/js/router/index.ts`

Meaning in practice:
- Manager and superadmin sections are hidden/blocked in UI by role meta.
- Nurse/general area includes most operational pages.
- API still enforces final permission decisions.

## 6. Known Gaps / Caveats

1. Global role name mismatch risk:
- Policies check `'super-admin'` in base helper, while seeder role is `'superadmin'`.
- Sources: `app/Policies/BasePolicy.php`, `database/seeders/Static/RoleSeeder.php`

2. `role:any` is broad by design:
- It allows any authenticated role (including fallback nurse).
- Endpoint safety then depends on `can:*` policies and request/controller scoping.

3. Not all routes use `can:*` middleware:
- Some rely only on role middleware and internal query constraints.
- For high-sensitivity data, prefer adding policy middleware where route model binding exists.

## 7. Recommended Ongoing Rules

1. Keep global reference tables read-any / write-superadmin.
2. For any route with `{patient}` or `{document}`, always pair with `can:view,patient/document` (or update/delete equivalent).
3. Keep manager endpoints at `role:manager,superadmin` and enforce same-company in services/policies.
4. Keep nurse operations branch/patient scoped in policy or FormRequest `authorize()`.
5. Add/maintain feature tests for cross-company and cross-branch denial paths (403).
