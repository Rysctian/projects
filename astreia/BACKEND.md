# Backend Coding Conventions

## 1. Core Principles

Use Laravel conventions. Keep architecture simple, readable, and maintainable.

**Preferred flow:**

Route → Controller → Model → Database

For complex operations:

Route → Controller → Service → Model → Database

Services are optional, not mandatory.

Use a Service only when logic is:
- Genuinely complex
- Reused across multiple places
- Coordinating significant multi-model workflows
- Managing complex transactions
- Integrating with external services

Do not introduce unless justified:
- Repository classes
- DTOs
- Action classes
- Interfaces
- Extra architectural layers
- Unnecessary folders

Prefer Laravel built-in features over custom abstractions.

**Avoid abstraction for abstraction's sake.**

## 2. Controllers

Controllers should primarily:
- Receive requests
- Authorize operations
- Validate input
- Coordinate operations
- Return Inertia responses, redirects, or other appropriate responses

Rules:
- Simple CRUD may remain inside controllers.
- Use inline validation for straightforward cases.
- Prefer Form Requests for complex or reusable validation.
- Move business logic out only when it becomes difficult to maintain.
- Avoid excessively large controller methods.
- Do not create a Service for simple CRUD operations.

## 3. Models

Models may contain:
- Relationships
- Attribute casts
- Query scopes
- Accessors and mutators
- Small model-specific behavior

Rules:
- Keep models focused.
- Avoid large business workflows inside models.
- Use Eloquent relationships instead of unnecessary manual queries.
- Prevent N+1 queries using appropriate eager loading.
- Avoid unnecessary database queries inside loops.
- Protect against mass-assignment vulnerabilities.

## 4. Validation and Authorization

- Validate all untrusted input on the backend.
- Use validated data for model creation and updates.
- Never rely solely on frontend validation.
- Use Laravel policies or gates for authorization.
- Enforce permissions on the server, not just through hidden UI controls.
- Scope queries to the user's permitted projects, workspaces, or resources.
- Never trust client-provided ownership or permission values.
- Follow existing authentication and authorization conventions.

## 5. Database and Transactions

- Prefer Eloquent and Laravel Query Builder.
- Use raw SQL only when justified.
- Use database transactions when multiple writes must succeed or fail together.
- Do not create a Service solely because a transaction is needed.
- Add appropriate indexes, foreign keys, and unique constraints.
- Avoid unnecessary queries and excessive eager loading.
- Paginate potentially large datasets.
- Consider concurrency when multiple users can update the same workflow.
- Keep database operations readable and predictable.

## 6. Seeders and Factories

Create realistic development and testing data that reflects actual business scenarios.

Rules:
- Use factories for generating sample records.
- Create meaningful relationships between records.
- Prefer 5–10 sample records for relevant feature tables when useful.
- Do not enforce arbitrary record counts for every table.
- Include realistic workflow states and user roles.
- Update affected factories and seeders whenever the schema changes.
- Keep development sample data separate from required system data.
- Ensure seeders work with a fresh database.

Avoid creating meaningless records solely to meet a minimum count.

## 7. Migrations

The project is currently in active development.

A disposable local development database may be rebuilt using:

`php artisan migrate:fresh --seed`

Rules:
- Keep migration history clean and understandable.
- Prefer updating the original `create_*` migration for schema changes when the database is disposable.
- Avoid unnecessary `update_*`, `alter_*`, or `revise_*` migrations during this stage.
- Merge obsolete development-only patch migrations into the original table creation migration when safe.
- Remove obsolete migrations only after verifying dependencies and environment usage.
- Update affected models, factories, and seeders.
- Maintain valid foreign-key creation order.
- Preserve appropriate constraints and indexes.

**Safety requirements:**
- Never run destructive database commands without explicit authorization.
- Confirm the target database connection before rebuilding.
- Never assume that local or development databases are disposable.
- Never rewrite migrations already relied upon by production, shared environments, or databases containing data that must be preserved.
- Use forward migrations once schema history must be preserved.

## 8. Business Logic and Workflow Integrity

- Keep business rules enforced on the backend.
- Validate allowed state transitions.
- Do not allow users to bypass required approvals.
- Preserve relationships between tickets, documents, projects, and QA records.
- Use transactions for related writes requiring atomicity.
- Track important workflow changes when auditing is required.
- Handle concurrent updates when they could violate workflow rules.
- Keep AI-generated changes in draft or review states until approved where required.
- Never allow AI suggestions to bypass authorization or approval rules.

## 9. Background Jobs and Integrations

- Use Laravel queues for long-running or retryable background operations when appropriate.
- Keep external API credentials in secure configuration.
- Do not hardcode credentials or secrets.
- Handle external service failures gracefully.
- Avoid duplicate side effects during retries.
- Keep integrations isolated when their complexity justifies it.

## 10. Code Rules

- Prefer simple, readable code.
- Keep methods focused.
- Avoid duplicated logic.
- Use descriptive names.
- Prefer existing project conventions.
- Avoid unnecessary comments.
- Avoid deeply nested conditions.
- Use early returns when clearer.
- Do not modify unrelated files.
- Do not create files unless needed.
- Avoid unnecessary dependencies.
- Preserve existing behavior unless a change is required.
- Do not overengineer solutions.

## 11. Testing

- Add or update tests for meaningful behavior changes.
- Test validation and authorization rules.
- Test critical workflow transitions.
- Test database relationships and constraints when relevant.
- Test important failure scenarios.
- Use factories to create realistic test data.
- Follow the existing test framework and conventions.
- Run relevant tests after changes.
- Report any tests that could not be executed.

Do not add excessive tests for trivial implementation details.

## 12. Change Safety

Before modifying backend code, inspect relevant:
- Controllers
- Request validation
- Models and relationships
- Routes
- Database schema and migrations
- Existing services, when applicable
- Related modules affected by the change
- Applicable tests

For risky changes:
- Keep changes scoped.
- Preserve existing behavior where possible.
- Avoid unrelated refactoring.
- Do not rename or move files without a clear reason.
- Identify affected consumers and dependencies.

## 13. Specification Workflow

Follow the repository-root `AGENTS.md`.

Before implementing meaningful changes:
- Read `SDD.md` and the owning module specification when available.
- Understand existing requirements and business rules.
- Identify affected behavior, data, permissions, and interfaces.
- Update specifications when contracts materially change.
- Document intentional exceptions when required by the existing specification workflow.
- Avoid unnecessary documentation changes for internal-only refactors that do not change contracts.
- Run available documentation checks and relevant tests.

Do not introduce additional specification files unless necessary or required by the repository's existing workflow.

## 14. General Rule

**Choose the simplest solution that remains clean, secure, correct, and maintainable.**

Do not overengineer.

Do not introduce architecture simply because it is a known design pattern.

Add abstraction only when the code genuinely needs it.

**Favor consistency, clarity, and maintainability over architectural complexity.**

