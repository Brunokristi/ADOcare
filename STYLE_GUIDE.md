# Code Guide — Functional & Stylistic Rules

This single document describes both the functional (architectural) and stylistic (formatting) rules for the codebase. It is intended for human contributors and automated agents alike — read it before making changes.

Quick summary
 - Keep controllers thin and delegate business logic to services.
 - Scope list GET endpoints to the most specific context (e.g. `/v1/branches/2/users` or `/v1/my-company/users`).
 - Use Resource/Collection classes for API responses and the ApiResponse trait (`$this->success()` / `$this->error()`).
 - Prefer many small, well-documented functions; prefer `use` imports and 4-space indentation.

If you'd like formal checks (PR checklist, linters or actions) added, open an issue or request it in the PR description.

---

## Audience

This guide is meant for both humans and automated agents that modify the codebase. When an agent is unsure about a guideline or needs to intentionally deviate, it must ask for confirmation and explain the tradeoffs.

---

## Functional Guidelines (Architecture & API)

- API design
	- GET list endpoints should be scoped to the **most specific reasonable context** (prefer branch-level over company-level when applicable).
	  - **Branch-scoped resources** should use `GET /v1/branches/{id}/...` (do not prefix with `/companies/{id}`).
	  - **Company-scoped resources** (no branch) should use `GET /v1/my-company/...` or, for superadmins, `GET /v1/companies/{id}/...`.
	- Entity operations (GET/PUT/DELETE/POST on a single resource) must address the entity directly: `PUT /v1/users/{id}`, `GET /v1/users/{id}`.

- Controllers & Services
	- Controllers should handle request validation, authorization checks and transform request data into service calls. Move business logic to `app/Services/*` classes.
	- Service methods should be focused, small, and independently testable.
	- Prefer Laravel's route-model binding: **do not manually fetch an entity by ID when the ID is supplied in the route** — accept the model in the controller method signature and let the framework resolve it. This keeps controllers concise and leverages automatic 404 behavior and implicit authorization hooks (e.g. `$this->authorizeResource()`).
	- Frontend (Vue) API calls should use the centralized `useApi()` composable where possible to ensure consistent request handling (loading, error, auth token headers, and company/branch scoping) instead of ad-hoc axios calls.
	- Prefer calling `useApi()` for all frontend API requests; it will automatically prefix the request path with the default API version (e.g. `/v1/`) and provides a `version` option when you need a different API version.

		// Bad: controller fetching model manually
		public function update(Request $request, $branchId)
		{
			$branch = Branch::findOrFail($branchId);
			$this->authorize('update', $branch);
			// ...
		}

		// Good: use route-model binding
		public function update(Request $request, Branch $branch)
		{
			$this->authorize('update', $branch);
			// ...
		}

		// Exception: fetch manually only when you need a different query (scoped lookup, soft-deleted include, or custom join).

- Responses
	- Always use the ApiResponse methods (`$this->success()` / `$this->error()`) in controllers.
	- Return Eloquent Resource classes for single entities and Collection classes for lists.
	- Use appropriate HTTP status codes (201 for created, 204 for successful delete with no content, 422 for validation errors, etc.).

- Documentation & docstrings
	- Add PHPDoc-style docblocks to all public classes and methods (controllers, services, helpers). Document parameters and return types and include a one-line description.

- When to break the rules
	- If a change will violate a guideline, ask for confirmation and record the reason in the PR. State which guideline is being broken and why, and propose mitigations.

- Database access
	- Prefer using Eloquent models and query builder for data access and relationships. Avoid raw SQL queries or `DB::statement` unless there is a demonstrated, unavoidable need (e.g., specific performance optimizations or vendor-specific SQL features).
	- If you must use raw SQL, document the reason in the PR and or in a code comment, use parameter binding to avoid SQL injection.

- Console logs & debugging
	- Remove `console.log` and other temporary debug prints from committed code. Use structured logging on the backend and proper debug tools locally.

- Static analysis & typing
	- Enable/encourage TypeScript strict mode and add ESLint rules to discourage `any` (e.g., `@typescript-eslint/no-explicit-any`).
	- Consider adding PHP static analysis (PHPStan or Psalm) to the CI pipeline to catch type and return-type issues early.

- Large data processing
	- When processing large datasets, prefer `cursor()` or `chunk()` to avoid loading entire tables into memory. Use transactions when mutating multiple related rows.

- Validation
	- Prefer FormRequest classes for complex request validation and authorization instead of inline `$request->validate()`.

- Authorization & security policies
	- Enforce authorization **server-side** for every endpoint — never rely on client-side checks.
	- Preferred enforcement points (use the first that fits the requirement):
		1. **Middleware** — coarse, route-level guards (roles, feature flags, subscription). Use when the check does not require a model instance and you want to fail fast.
		2. **Policies / Gates** — model-scoped permissions (use `AuthServiceProvider` to register policies and call `$this->authorize('update', $model)`).
		3. **FormRequest::authorize()** — request-level checks that depend on request input (e.g. `branch_id`) and should run alongside validation.
		4. **Controller / service method checks** — only when authorization depends on runtime context not available elsewhere.

	- Guidelines:
		- Use middleware for broad access control (e.g. `->middleware('role:manager')`).
		- Use policies for per-model permissions (e.g. `PatientPolicy::update`).
		- Use FormRequest::authorize for validating request-scoped access (e.g. verifying the `branch_id` belongs to the user's company before creating a patient).
		- Prefer `$this->authorize()` in controllers rather than ad-hoc DB checks — centralize logic in policies for testability.

	- Examples:

		- Middleware (route):

			Route::post('/admin-only', [AdminController::class, 'store'])->middleware('role:admin');

		- Policy (controller):

			public function update(Branch $branch)
			{
				$this->authorize('update', $branch);
				// ...
			}

		- FormRequest (authorize + rules):

			public function authorize()
			{
				$branch = Branch::find($this->input('branch_id'));
				return $this->user()->can('view', $branch);
			}

	- Security hygiene:
		- Log unexpected authorization failures at WARN level (avoid sensitive data in logs).
		- Deny by default; prefer explicit allow rules.
		- Keep authorization logic DRY — centralize in policies/middleware rather than sprinkling checks across services.

- Documentation & API docs
	- When adding or changing API endpoints, update Scribe/OpenAPI docs and include examples in the PR.


---

## Stylistic Guidelines (Formatting & Conventions)

- Indentation & formatting
	- Use 4 spaces for indentation (no tabs).
	- Keep lines concise and split long expressions across lines for readability.

- Imports & references
	- Prefer `use` statements to import classes (group and order imports where practical).

- Docstrings & comments
	- Use PHPDoc for public APIs and add short explanatory comments for non-obvious decisions.

- Types & TypeScript rules
	- **Never use `any`** unless there is a very specific, unavoidable reason. Prefer explicit types, interfaces, or generics so code is self-documenting and type-safe.
	- Enable and respect strict TypeScript options where possible (noImplicitAny, strictNullChecks, etc.).
	- Prefer typed DTOs / interfaces for API payloads and service inputs/outputs.

- Early returns & descriptive naming
	- Prefer early returns to reduce nesting and clarify flow. Example:
		- Good: `if (!user) return null` then proceed; avoid nested if/else blocks.
	- Use clear, descriptive variable names rather than short names or magic-number variables.
	- Avoid magic numbers and repeated literals; extract them into named constants.

- Service usage & dependency injection
	- Do **not** make service classes static. Instantiate services via constructor injection in controllers so they can be mocked and tested easily.
	- Prefer dependency injection patterns supported by the framework.

- Error handling & logging
	- Do not silently catch and ignore exceptions. Catch only when you can recover or convert to a meaningful API response.
	- Use structured logging for unexpected errors and avoid leaving `console.log` / debug prints in committed code.

- Reusability & organization
	- Favor small, well-documented functions over large ones. Extract common logic into services or helpers s.

- Language & internationalization
    - Be consistent with language in code and messages. The primary languasge is Slovak for anything that the user sees (UI, API messages) and English for code, variable names, and technical comments. Avoid mixing languages in the same context (e.g., don't use Slovak variable names or English messages).

---

## Frontend / Vue guidelines

 - **Keep `App.vue` thin.** The main application component should only contain the absolute minimum (router outlet, global providers, layout scaffolding). Any real logic, data fetching, or UI elements should be moved into child components or composables.  If you find yourself adding watchers, stores, or service calls directly in `App.vue`, the code should be abstracted into a dedicated component or utility instead. This prevents the root component from becoming a catch‑all and keeps the global structure easy to reason about.


---

## Practical Examples

- Good: `UserController::index()` calls `UserService::listForCompany($companyId, $filters)` and returns `$this->success(new UserCollection($results))`.
- Bad: `UserController::index()` builds complex joins and returns raw arrays.
