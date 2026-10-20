# Astreia Phase 01 core

## Running locally

Dependencies already installed: PHP/Composer, Node/npm, Laravel Vue/Inertia starter, Tailwind/shadcn-vue, Pest, Vitest and Playwright. No Docker setup is used.

The inspected Node runtime is 22.14.0. The installed Vite Plus 0.3.0 declares Node `^20.19.0 || ^22.18.0 || >=24.11.0`; builds and tests passed locally, but update to a supported runtime before deployment. No runtime was installed or upgraded by this implementation.

1. Configure an actual supported MySQL server in `.env` using your existing credentials. Keep `DB_CONNECTION=mysql`, InnoDB and `utf8mb4`. The inspected local endpoint at `127.0.0.1:3307` identifies itself as **MariaDB 10.4.32**, so it is not validation against the required MySQL engine. No database server has been replaced or installed.
2. Inspect `php artisan migrate:status`, then apply pending forward migrations with `php artisan migrate`. Do not use `migrate:fresh` on a database with records to preserve.
3. Create the first administrator with `php artisan astreia:bootstrap-admin --name="Your Name" --email="you@example.com"`. The password is entered secretly and is never a command-line argument. The command refuses to create another bootstrap admin if an active one exists.
4. Configure SMTP, for example an existing local mail catcher at `127.0.0.1:1025`. Password setup/reset uses expiring Laravel password broker tokens. Public registration is disabled. An internal user verifies control of their email when using their password-setup token.
5. Run `npm.cmd run build`, then `php artisan serve` (or run separate PHP/Vite processes for development). Open `/login`.

## Core operating process

1. System Admin creates internal users and sends password setup links.
2. System Admin creates a project with a permanent code. It receives editable starter roles and a published workflow: Backlog → Ready → Development → QA → Closed, including QA → Development for failure.
3. Add existing accounts as project members and assign configurable roles. Appoint a member with project access management if day-to-day delegation is needed. Role names never grant authority by themselves; grants and deny overrides determine access.
4. Create a ticket or use urgent support intake to immediately obtain a key with no invented details.
5. Complete requirements and acceptance criteria, and assign an eligible active project member before work becomes ready.
6. Follow the project's allowed transitions. Record review/QA findings and use the configured return path for failed verification.
7. Close with current QA verification where required, all applicable checkpoint approvals, and completion notes. Feature/enhancement closure records documentation impact; `not_required` needs a reason. This phase does not publish documents.
8. Block/resume/cancel/reopen with a reason. Reopening keeps the key and history, and invalidates previous verification. Accounts/memberships are deactivated and projects/modules are archived rather than deleting history.

## Project workflow changes

Use project Workflow to clone or create a draft, edit stages and connections, then publish. Published versions cannot be edited through application endpoints. New tickets use the new workflow; existing tickets retain their assigned workflow. There is no implicit ticket migration between versions.

## Concurrency and authorization

Ticket numbers are allocated under a project/counter row lock. Ticket mutations include `lock_version`; stale forms fail without overwriting the latest state. Material requirements increment `revision_number`. Sign-offs include the requirement revision, verification cycle and stage visit so an old approval cannot be reused after a stage return or reopen. All transitions and history writes occur transactionally.

Project roles and user overrides apply only within their project. Access managers may not grant permissions they lack, including through membership reactivation. An explicit deny wins. Global System Admin access still requires an active account. Existing sessions are rejected after account deactivation. Private attachment downloads verify both project access and ticket ownership.

## Attachments pending approval

`ATTACHMENT_UPLOADS_ENABLED=false` by default. The attachment path is private and has no public file-serving route. Before enabling uploads, approve the extensions and size limit, then set `ATTACHMENT_ALLOWED_EXTENSIONS` and `ATTACHMENT_MAX_KILOBYTES`. The default configurable size is 10240 KiB; it is a proposed setting, not an approved rollout policy. Limits and server-detected MIME types are validated when uploads are enabled.

## Verification

- `php artisan test --compact`: isolated in-memory SQLite, never the application database.
- `php vendor/bin/phpstan analyse --no-progress`: backend static analysis.
- `php vendor/bin/pint --test`: backend formatting.
- `npm.cmd run test:unit`, `npm.cmd run types:check`, `npm.cmd run check`, `npm.cmd run build`.
- `npm.cmd run test:e2e`: isolated temporary SQLite fixture and a dedicated server on port 8017. It creates test-only accounts, uses array mail, and does not use or reset the application database. Existing Playwright Chromium is required; this implementation does not download browsers.

The stage board covers all matching project tickets, with independent pagination per stage and separate columns for historical workflow stages. Basic reporting covers full-project stage/category totals and open workload by assignee. Native MySQL concurrency/constraint integration and real SMTP delivery remain environment checks. Portable SQLite tests do not establish MySQL locking behavior.

Latest verification: 78 backend tests (397 assertions), 4 Vue unit tests and 2 Chromium acceptance tests passed; PHPStan level 7, Pint, frontend type checking, the scoped frontend lint/format check and the production build passed. The whole-repository formatter still flags six existing user files: `AGENT.md`, `BACKEND.md`, `FRONTEND.md`, `docs/SDD.md`, the phase specification and `skills-lock.json`; those files were preserved.
