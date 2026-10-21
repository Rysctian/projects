# Secure core implementation plan

**Goal:** Implement research Phase 01 for new projects while preserving existing project behavior and history.

**Spec:** `docs/jira-research/phases/PHASE_01.md`; accepted compatibility decision in `docs/changes/2026-10-21-secure-core.md`.

**Architecture:** Existing Laravel/Vue/Inertia application, forward migrations, project process discriminator. Reuse scoped records and history. No new packages, Docker, database reset or automatic existing-project conversion.

## Tasks

- [ ] Access/onboarding: explicit global permissions, invitation lifecycle, core membership checks, audited recovery and archive write guards. Own `ProjectAccess`, `AccountService`, user authorization and invitation code; focused security tests.
- [ ] Core data/lifecycle: add project process/timezone/version and scoped work-type/priority/resolution catalogs; seed published core graph; enforce intake, assignment, outcomes, reopen and optimistic locking. Own migrations, catalog models, `TicketService`, `WorkflowService`, project/ticket controllers and tests.
- [ ] Frontend: core-aware creation/edit/transition forms, catalogs, resolution history, filtered totals/sorting, archive/restore reasons/timezone, reassignment warning; hide deferred core features while preserving legacy UI.
- [ ] Fixtures: retain legacy tests as explicit legacy fixtures; add realistic idempotent core demo data and catalog factories. Preserve established credentials.
- [ ] Verification: lifecycle and cross-project tests, archive and stale-write tests, filter/totals/timezone tests, legacy regression suite, PHPStan/Pint, TypeScript/unit/build/browser checks as available; independent final review.

## Review focus

Core admins without membership must not read tickets; archived writes cannot exploit admin bypass. Cross-project catalogs and retired values must not enter tickets. Resolutions clear on reopen but remain in history. Null/zero estimates differ. Date totals follow the project timezone and active filters with stable sort ties. Existing workflow IDs and category meaning remain unchanged.

## Progress

- Baseline: `php artisan test --compact` passed 81 tests, 469 assertions using forced SQLite in memory.
- Owner confirmed new-project core / existing-project preservation. Shared interfaces below are implementation choices consistent with that decision.
- Project discriminator: `process_model` = `legacy` for existing/factory projects, `core` for new HTTP-created projects; `Project::isCore(): bool`.
- Core catalog fields: `work_type_id`, `priority_id`, `resolution_id`; catalog rows expose `id`, `code`, `name`, `is_active`, with priority `rank` and resolution `outcome`.
- Existing ticket `title`, `module_id`, `assigned_to`, `closed_at`, `completion_notes` stay the wire names. Core UI labels them Summary, Component, Assignee and Resolved. Existing string category/priority remain compatibility projections of catalog codes.
