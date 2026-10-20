# Phase One Core Implementation Plan

> For agentic workers: use executing-plans task by task; track verification here.

**Goal:** Deliver the internal project → request → assignment → configured workflow → verification → closure process before secondary reporting work.

**Architecture:** Retain the Laravel/Vue/Inertia monolith. Use Eloquent and policies/gates, with focused services for access changes, workflow publication and transactional ticket lifecycle operations. Keep historical actors, ticket keys, workflow versions and requirement revisions stable.

**Tech Stack:** Laravel 13, Vue 3, TypeScript, Inertia 3, Tailwind 4, MySQL, Pest/Vitest/Playwright already installed.

**Spec:** `docs/phases/astreia-phase-1-foundation.md`, with `docs/SDD.md`, `AGENT.md`, `BACKEND.md`, `FRONTEND.md`.

## Global constraints

- No Docker or new dependency installations.
- Configurable project roles, explicit grants and deny overrides; only active System Admin accounts bypass project permissions.
- All server reads and mutations enforce project scope, active accounts and archived-project restrictions.
- Five fixed ticket categories, four priorities, nullable minute estimates.
- Immutable published workflows and old ticket workflow references. No document/AI functionality.
- Do not rebuild or mutate existing databases automatically. Forward migrations preserve potentially existing data.
- Use isolated in-memory SQLite for portable tests; distinguish these results from actual MySQL verification.

## Review focus

- Access managers must not amplify their own authority through role edits, member assignment or overrides.
- Stale requirement revisions, failed/repeated stage visits, concurrent edits and reopening must invalidate old sign-offs.
- Crafted cross-project IDs in tickets, roles, workflow endpoints, assignees and downloads must fail.
- Disabled accounts must lose existing sessions as well as password authentication; preserve last-admin protections.
- Shortcut transitions to closed must still enforce readiness, QA, completion and documentation-impact rules.

## Task 1: Identity

Files: user access flag migration; `User`; active-account middleware; Fortify auth; bootstrap-admin command; admin user controller; identity tests.

Interfaces: `User::hasProjectPermission(Project $project, string $key): bool`; active middleware on web routes; `astreia:bootstrap-admin` interactive command.

- [x] Test registration disabled, inactive login/session rejection, admin-controlled invitations and final-admin protection; observe failures.
- [x] Implement active/admin flags using a forward migration, password setup via Laravel broker, secret-free auditing and account deactivation instead of hard deletion.
- [x] Run identity tests and starter authentication/settings tests.

## Task 2: Projects and project access

Files: project/access/audit migration; models; `ProjectAccess` service; project policy/controller; access controller; project/access tests.

Interfaces: project index/show/store/update; module CRUD; member/role/override endpoints; permission catalog; permission keys exposed by `ProjectAccess::KEYS`.

- [x] Test scoped visibility, deny precedence, role names having no authority, cross-project roles and privilege escalation; observe failures.
- [x] Implement projects/modules, dynamic access and audits. New projects receive a default workflow and editable role templates.
- [x] Test last-access-manager and archived-project behavior, then run suite.

## Task 3: Configurable workflows

Files: workflow migration/models; `WorkflowService`; workflow controller; workflow tests.

Interfaces: `WorkflowService::saveDraft(Project $project, User $actor, array $data): ProjectWorkflow` and `publish(Project $project, User $actor, ProjectWorkflow $workflow): ProjectWorkflow`; draft/save/publish endpoints using stage stable keys.

- [x] Test immutable workflow versions, exactly one intake initial stage, connected/closed reachability, invalid endpoints and old tickets retained; observe failures.
- [x] Implement default template, editable drafts and transactional publication with project locks.
- [x] Run workflow/access tests.

## Task 4: Ticket lifecycle

Files: ticket/counter/comment/activity/sign-off migration; ticket models; `TicketService`; ticket request/controller; lifecycle tests.

Interfaces: nested ticket list/create/show/update; transition/sign-off/comment/block/resume/cancel/reopen endpoints; optimistic `lock_version` in mutation requests, separate from `revision_number`.

- [x] Test real urgent ticket keys, incomplete-intake restrictions, assignment eligibility and acceptance readiness; observe failures.
- [x] Implement project-row-locked numbering and validated scoped CRUD.
- [x] Test forbidden jumps, QA failures/returns, stale approvals, manual stage gates, mandatory closure, blocked resume and reopening; observe failures. Cancellation follows the same locked terminal-state action boundary.
- [x] Implement transactional lifecycle and immutable actor/time activities; retain documentation impact as data only.
- [x] Run complete backend suite.

## Task 5: Usable core screens

Files: project index/detail/settings/workflow pages; ticket list/detail; admin users page; navigation/types; component tests where behavior warrants them.

Interfaces: typed Inertia props from the above controllers; existing UI primitives and `useForm`; server-supplied permission capabilities.

- [x] Build complete forms and error states for the tested endpoints, with a focused ticket list before secondary dashboard features.
- [x] Provide workflow stage/transition editor and read-only published version display; role permission/member controls.
- [x] Run frontend types, formatting/lint and production build.

## Task 6: Collaboration and handoff

Files: private attachment policy/config/controller and tests; labels/filter/report endpoints/screens as core dependencies permit; core E2E test and handoff.

- [x] Protect private downloads; keep uploads disabled until file type/size policy is approved.
- [x] Complete list filters, stage board/basic counts and labels after core flow passes. Board pagination spans the project; stage/category/workload reports added.
- [x] Run available tests, independent code review, fixes and final build; record actual database/browser limitations and startup steps.

## Progress and decisions

- Inspection: only starter identity/settings exist. User's `AGENT.md` is untracked; preserve it.
- Authorization: user explicitly requested implementation of this phase, core process first. No additional design approval required.
- Execute in the existing checkout on a new local feature branch so the requested files stay visible; no worktree, commits or remote changes required.
- Pending: confirm attachment rollout policy; uploads remain off by default.
- Verification: 78 backend tests / 397 assertions, 4 frontend unit tests and 2 Chromium acceptance tests passed. PHPStan level 7 and Pint passed. Frontend type checks, scoped formatter/linter (84 files), production build and `git diff --check` passed.
- Whole-repository `npm run check` reports existing formatting in `AGENT.md`, `BACKEND.md`, `FRONTEND.md`, `docs/SDD.md`, the phase specification and `skills-lock.json`; these user-owned files were left unchanged.
- Independent review: four important findings were reproduced with real integration tests and fixed (rework approvals, executable workflow gates, usable final-access-manager protection, date-safe assignment-only edits). No critical finding established. Native MySQL locking/schema behavior and real SMTP delivery remain external validation; configured database identifies as MariaDB 10.4.32.
- Scope: no new packages, services or Docker configuration installed. The original draft and engineering conventions remain intact; startup/operating instructions are in `docs/CORE_HANDOFF.md`.
