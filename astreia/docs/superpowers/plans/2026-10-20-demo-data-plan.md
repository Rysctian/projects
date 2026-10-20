# Phase 1 Demo Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide factories and a safe, realistic seed dataset for every Phase 1 domain table.

**Architecture:** Each Eloquent domain model exposes a conventional factory with valid foreign-key defaults. `AstreiaDemoSeeder` assembles a deterministic connected workspace so the data represents real access, workflow, ticket, QA, collaboration, labeling, and audit scenarios. Framework runtime tables and file blobs are intentionally excluded.

**Tech Stack:** Laravel 13, Eloquent factories, Pest, SQLite test database.

**Spec:** `AGENT.md`, `BACKEND.md`, and `docs/phases/astreia-phase-1-foundation.md`

## Global Constraints

- Do not run destructive database commands or modify the configured local database.
- Keep sample data separate from required system behavior and do not add dependencies.
- Seed only data that respects foreign keys, project isolation, configurable permissions, and workflow history.
- Docker remains out of scope.

## Review Focus

- Re-running the development seeder must not create duplicate identities or a malformed project graph.
- Seeded role and membership pivots must never cross project boundaries.
- Every seeded ticket must use a stage from its own workflow and project-scoped module/label/assignee.
- A seeded workflow must have a publishable intake-to-verification-to-closed path.
- Attachment records must remain metadata-only and point at the private disk; seeders must not create arbitrary files.

---

### Task 1: Prove the demo dataset contract

**Files:**
- Create: `tests/Feature/Core/DemoDataSeederTest.php`

- [x] Write a failing integration test that runs `AstreiaDemoSeeder` twice and verifies deterministic users, projects, workflows, tickets, collaboration records, labels, access pivots, audit records, and counters exist without duplication.
- [x] Run the test and confirm it fails because the demo seeder does not exist.

### Task 2: Add valid domain factories

**Files:**
- Create: `database/factories/*Factory.php`
- Modify: `app/Models/*.php`

- [x] Add `HasFactory` support and factory defaults for each Phase 1 Eloquent model, preserving all scoped foreign-key constraints.
- [x] Run the focused test and confirm the factory-backed demo dataset passes.

### Task 3: Assemble the deterministic demo workspace

**Files:**
- Create: `database/seeders/AstreiaDemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [x] Implement an idempotent demo seeder with named accounts, two projects, permissions/roles/members/overrides, modules, current published workflows, tickets in varied states, all ticket collaboration records, labels/pivots, counters, and audit logs.
- [x] Register it with the default development `DatabaseSeeder` and run the focused test again.

### Task 4: Verify and review

**Files:**
- Test: `tests/Feature/Core/DemoDataSeederTest.php`

- [x] Run the focused and complete PHP suites, PHPStan, Pint, and diff whitespace checks.
- [ ] Review the changed code against the Review Focus items and document any limitation in the handoff.
