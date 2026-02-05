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
	- GET list endpoints should be scoped to the most specific reasonable context: prefer `GET /v1/branches/{id}/users` or `GET /v1/my-company/users` instead of a broad `GET /v1/users` when context is available.
	- Entity operations (GET/PUT/DELETE/POST on a single resource) must address the entity directly: `PUT /v1/users/{id}`, `GET /v1/users/{id}`.

- Controllers & Services
	- Controllers should handle request validation, authorization checks and transform request data into service calls. Move business logic to `app/Services/*` classes.
	- Service methods should be focused, small, and independently testable.

- Responses
	- Always use the ApiResponse methods (`$this->success()` / `$this->error()`) in controllers.
	- Return Eloquent Resource classes for single entities and Collection classes for lists.
	- Use appropriate HTTP status codes (201 for created, 204 for successful delete with no content, 422 for validation errors, etc.).

- Documentation & docstrings
	- Add PHPDoc-style docblocks to all public classes and methods (controllers, services, helpers). Document parameters and return types and include a one-line description.

- When to break the rules
	- If a change will violate a guideline, ask for confirmation and record the reason in the PR. State which guideline is being broken and why, and propose mitigations.

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
	- Favor small, well-documented functions over large ones. Extract common logic into services or helpers and add tests.

- Tests
	- Add unit tests for service logic and integration tests for API behavior when adding or changing business logic.

---

## Practical Examples

- Good: `UserController::index()` calls `UserService::listForCompany($companyId, $filters)` and returns `$this->success(new UserCollection($results))`.
- Bad: `UserController::index()` builds complex joins and returns raw arrays.

---

## PR checklist (suggested; please enable as a template)

- Add/update unit/integration tests for new business logic
- Add PHPDoc for new public methods/classes
- Return Resource/Collection classes for endpoints
- Ensure controllers delegate logic to services
- Run linters, type checks and tests locally

- Avoid using `any` in TypeScript; prefer explicit types and DTOs
- Ensure services are instantiated via constructor injection in controllers (no static services)
- Replace magic numbers with named constants and prefer descriptive variable names

---

## When an agent or developer is unsure

- Ask the repository owner or create a short PR describing suggested changes and how they affect the guidelines.
- If the change must break the guidelines (e.g., performance emergency), explicitly document the exception and ensure a follow-up to restore compliance.

---

If you'd like, I can add a PR template and a minimal GitHub Action that verifies some of these rules automatically (e.g., PHPDoc presence, resource usage, basic linting). Let me know if you want that added.