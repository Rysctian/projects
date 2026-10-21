# Astreia — System Design Document (SDD)

> **Status:** Draft — architectural reference; not blanket authorization to implement future phases  
> **Version:** 0.1 | **Date:** October 20, 2026  
> **Scope:** Entire Astreia product, delivered incrementally  
> **Current implementation specification:** `docs/phases/PHASE_01_FOUNDATION.md` (v0.4, MySQL)

## 1. System overview

**Astreia** is a project, ticketing, process-documentation, and workflow management platform for teams of coordinators, developers, and testers. Coordinators receive client requirements, maintain **user-facing process guides**, create and verify tickets, and coordinate delivery. Developers implement and discuss the requested work. Astreia connects these activities in one auditable workspace.

Its defining capability is **bidirectional ticket ↔ documentation management**: a ticket can lead to a proposed guide revision, or a coordinator can edit a process guide, select the changed content, and create a linked ticket. Proposed feature documentation remains a **draft** until the work is completed, verified, and approved. Later AI assistance will analyze guides, suggest revisions, identify affected processes, draft test cases, and generate flowcharts. AI does not decide business rules or publish content.

**Documentation means operating procedures and user guides**, with steps, conditions, screenshots, and flowcharts—not automated source-code or API documentation.

### Outcomes

- One source of truth for projects, requirements, tickets, approvals, and process guides.
- Less repeated writing across tickets, Word documents, diagrams, and QA records.
- Traceability from client request → implementation → verification → approved guide version.
- Flexible project teams and workflows without hard-coded job titles or stage names.
- Reliable audit, access control, and history as the product expands beyond its first client system.

## 2. Specification authority and scope

**2026-10-21 approved exception:** New projects follow `docs/jira-research/phases/PHASE_01.md`, with the compatibility and schema mapping recorded in `docs/changes/2026-10-21-secure-core.md`. Existing projects retain the foundation workflow and ticket category meanings described below. The research roadmap's later phases are not authorized by this exception.

| Source | Responsibility |
|---|---|
| `docs/SDD.md` | Product vision, system boundaries, invariants, logical architecture, and phase roadmap. |
| `docs/phases/PHASE_01_FOUNDATION.md` | Authoritative **Phase 01** business processes, MySQL starter schema, and deliverables once explicitly approved. |
| Future `docs/phases/PHASE_XX_*.md` | Authoritative requirements and acceptance criteria for each later phase, once reviewed and approved. |
| `AGENTS.md` and module documentation | Agent workflow, coding conventions, repository-specific implementation guidance. |
| `docs/changes/` | Approved decisions and changes affecting specifications or implementation. |

**Conflict rule:** A reviewed phase specification governs the details of **its own phase**. This SDD governs cross-phase intent. If the two conflict on architecture, permissions, data ownership, or publishing behavior, **flag the conflict and seek an explicit decision**; do not silently improvise. Earlier **ProcessSync** documents are background context; the current product name is **Astreia**, database is **MySQL**, and Phase 01 uses configurable roles and workflows.

**Implementation boundary:** Only implement the explicitly authorized phase/slice. Future features below are planned capabilities, **not approved migrations or tickets**.

## 3. Users, projects, and authorization

- **System Admin:** The **only unrestricted account type**. Manages all users, projects, permission catalog, and global administration. Protect the last active System Admin.
- **Project member:** An active user who belongs to one or more projects. Membership does not itself grant every action.
- **Project roles:** Customizable, project-scoped collections of permission keys. Coordinator, Developer, QA, and Reviewer may be **starter templates**, never hard-coded authorization identities.
- **User overrides:** Optional project-scoped, permission-level `allow`/`deny`. Explicit deny overrides grants except for System Admin.
- **Project access manager:** A member with authorized access-management permission; cannot administer other projects or grant capabilities beyond their own authority.

**Authorization invariant:** Effective access requires an active user, applicable project membership, permission grant, and relevant object/workflow conditions; default is deny. Enforce on the server for all reads, edits, attachments, workflow actions, document retrieval, and background jobs. Record access changes.

**Hierarchy:** `Application → Projects/Systems → Modules + Team + Tickets + Process Documents + QA`. Begin with one existing client system, but permit multiple projects in the data model.

## 4. Core functional modules

| Module | Responsibility |
|---|---|
| Identity & access | Authentication, user lifecycle, project membership, custom roles, permissions, and overrides. |
| Projects & modules | Client-system records, functional modules, membership, archiving, project settings. |
| Workflows | Project-defined, versioned ticket stages, transitions, approvals, and closure conditions. |
| Tickets | Request intake, category/priority, estimation, assignment, activity, attachments, tracking, closure. |
| Documentation | Import process guides, edit structured drafts, approve and version published guides, export files. |
| Ticket–document links | Trace tickets to specific document sections/selections and approved proposed changes. |
| QA & verification | Manual stage sign-offs initially; dedicated test cases, runs, and evidence in a later phase. |
| AI assistance | Document analysis, affected-section suggestions, grounded drafts, flowcharts, QA suggestions. |
| Audit & reporting | Actor/time history, project/workload coverage, document publication records, job outcomes. |

## 5. Essential business workflows

### 5.1 Project onboarding and access — Phase 01

1. System Admin creates internal user accounts; public registration is disabled.
2. System Admin creates a project with an immutable project code, then assigns initial authorized project access management.
3. Authorized project members configure roles, permissions, member access, modules, and a ticket workflow.
4. Every subsequent action is evaluated against the user's **effective project permissions**.
5. Deactivation or archiving blocks new unauthorized activity without deleting historical actors or tickets.

### 5.2 Ticket intake and execution — Phase 01

1. An authorized user creates a ticket under one project; the system issues a permanent key such as `HRIS-00024`.
2. Choose one of five stable categories: `new_feature`, `enhancement`, `bug_fix`, `documentation`, `support`; priority is `low`, `normal`, `high`, or `urgent`.
3. Record requirements, assignee, estimate (minutes), acceptance criteria, and evidence as applicable.
4. Move through the project's **published workflow version**, not a fixed global status sequence.
5. Required transitions, reasons, and sign-offs are validated; failed checks return to configured work stages.
6. An authorized closer completes the required gates and closes the ticket; closure and history are recorded atomically.

**Urgent exception:** A `support` + `urgent` ticket may be created immediately as an incomplete intake stub to obtain a ticket key for urgent fieldwork/commit tagging. It cannot advance from intake until essential details are completed.

**Workflow configuration:** Projects can add stages such as `Development 2`, `Code Review`, `Staging Deployment`, or `UAT`. Each stage has a stable identity and semantic type; only allowed transitions can be used. Workflow edits create a new **published version**. Existing tickets remain attached to their prior version until explicitly migrated with an audit record. Mandatory closure checks cannot be bypassed by configuring shortcuts.

**Phase 01 document boundary:** When a feature/enhancement ticket closes, record whether documentation changes are `required` or `not_required` (with rationale when not required). **No document is created or published in Phase 01.**

### 5.3 Existing process-guide onboarding — future phase

1. Coordinator uploads an existing DOCX user/process guide into the correct project/module.
2. Preserve the original file unchanged; convert supported content to an editable structured document.
3. Show conversion limitations for tables, screenshots, diagrams, and special formatting.
4. Coordinator checks and corrects the imported content before approving **baseline v1**.
5. Baseline becomes the immutable published reference; later edits occur as drafts inside Astreia.

### 5.4 Ticket-first documentation change — future phase

1. Coordinator creates a feature, enhancement, or relevant bug ticket.
2. Authorized staff or AI identify **candidate** affected guides and sections; a human confirms them.
3. Coordinator prepares a ticket-scoped proposed document change, with flowchart/QA updates where needed.
4. Development and required testing occur; coordinator/reviewer approves the proposed guide change.
5. Eligible ticket closure publishes **only the approved changes linked to that ticket** as a new immutable version.
6. DOCX/PDF export and notifications happen **after** publication, asynchronously when implemented.

### 5.5 Document-first ticket creation — future phase

1. Coordinator opens a published guide and creates a **working draft**.
2. The coordinator writes a proposed feature/process revision and selects a paragraph, step, or block.
3. **Create Ticket** captures the selected text snapshot and a stable section/block reference, along with ticket category and estimate.
4. The ticket links back to that document location; the draft remains unpublished while work proceeds.
5. After completion, QA, and approval, closure publishes that ticket's approved change set only.

### 5.6 Documentation-only changes and bug fixes — future phase

- **Documentation-only:** Correct a typo, screenshot, or instruction describing existing behavior. Allow a separate human-review publishing route without pretending a new feature was implemented.
- **Bug fix:** If a fix restores already-documented behavior, no guide revision is required. Update documentation only if the user-facing process actually changes.
- **Parallel changes:** Maintain ticket-scoped drafts from a known base version. Detect conflicting or stale edits before merging; never overwrite another ticket's published change silently.

## 6. Non-negotiable system rules

1. A document **draft** never represents verified or live functionality.
2. Feature/enhancement documentation cannot publish before its qualifying ticket is closed, tested, and approved under the future publishing policy.
3. Closing a ticket with no guide change must not create an unnecessary document version.
4. Published document versions and original imported files are immutable; corrections create new versions.
5. Ticket keys, historical stage identities, workflow versions, sign-offs, publication events, and actors remain traceable.
6. When a ticket requirement materially changes, previous verification tied to the old requirement revision is invalidated.
7. Publishing includes only ticket-scoped, approved, conflict-free changes; coordinate the eligible closure/publication DB state in one transaction.
8. DOCX export failure never rolls back or deletes a successfully published database version; retries are recorded.
9. AI produces reviewable, source-referenced suggestions; it cannot approve, close, change access, or publish autonomously.
10. Project isolation applies to document indexing/retrieval, attachments, exports, and AI context—not only ordinary web pages.

## 7. Technical architecture

**Architecture:** Laravel + Vue/Inertia **modular monolith** (one primary application and codebase). Use focused internal services for domain operations. Run heavyweight jobs as separate worker processes without prematurely splitting the core application into microservices.

| Layer | Selected direction |
|---|---|
| Backend | **Laravel 13**, or approved compatible stable release at bootstrap |
| Frontend | **Vue 3** latest compatible stable + TypeScript + Composition API |
| Application navigation | **Inertia.js 3** with Vite |
| Styling | Tailwind CSS 4 + shadcn-vue |
| Primary database | **MySQL**, InnoDB, `utf8mb4`, migrations and relational constraints |
| Authentication | Laravel session-based auth and secure password setup/reset |
| Authorization | Laravel policies + custom project-scoped access rules; third-party permission package optional only if model fits |
| Phase 01 files | Private Laravel filesystem; S3-compatible storage when needed |
| Background processing (later) | Laravel queues + Redis + Horizon for import, AI, rendering, and exports |
| AI (later) | Local Ollama + evaluated Qwen model; provider interface permits approved cloud fallback |
| Rich-text editing (later) | Tiptap/ProseMirror structured editor; preserve original DOCX |
| Flowcharts (later) | Mermaid for generated diagrams; Vue Flow if interactive graph editing is needed |
| DOCX production (later) | Validated import/conversion pipeline; PHPWord or an evaluated template/export service |
| Realtime (later) | SSE for job progress/notifications with polling fallback; collaborative editing may need separate synchronization infrastructure |
| Automated testing | Pest, Vitest, Playwright |
| Deployment | Docker + Nginx; hosting and AI hardware determined later |

**AI search approach:** Start with project-filtered MySQL full-text/section search in the relevant future phase. Evaluate embeddings and a compatible vector service **only if retrieval quality/scale warrants them**. Do not carry over PostgreSQL/pgvector assumptions from the older ProcessSync overview.

**Dependencies:** Install only what the active phase requires. Phase 01 needs PHP + MySQL (`pdo_mysql`), Laravel Vue/Inertia starter dependencies, test tools, private storage, and a development mail transport. Tiptap, Ollama, Redis/Horizon for AI, Mermaid, PHPWord, and collaboration tools are **later-phase dependencies**.

## 8. Data model and ownership

### 8.1 Phase 01 — MySQL foundation (defined in its phase specification)

| Domain | Tables |
|---|---|
| Identity/access | `users`, `sessions`, `password_reset_tokens`, `permissions`, `roles`, `role_permissions`, `project_members`, `project_member_roles`, `project_member_permission_overrides` |
| Projects | `projects`, `modules` |
| Workflows | `project_workflows`, `workflow_stages`, `workflow_transitions` |
| Tickets | `project_ticket_counters`, `tickets`, `ticket_stage_signoffs`, `ticket_comments`, `ticket_activities`, `ticket_attachments`, `project_labels`, `ticket_labels` |
| Cross-cutting | `audit_logs` |

**Important relationships:** Projects own modules, members, roles, workflows, labels, and tickets. Tickets reference a project, an immutable project-scoped number, a **specific workflow version and stage**, and optional eligible assignee/module. Roles grant permissions only within their project. Sign-offs record the ticket's requirement revision. Activity and audit records retain previous values/actors.

**MySQL rules:** `BIGINT UNSIGNED` IDs/FKs, UTC timestamps, native `JSON` (not PostgreSQL `JSONB`), InnoDB transactions, foreign keys, unique constraints, and appropriate indexes. Allocate ticket keys through a per-project row-locked counter; never use unsafe `MAX(number)+1`. Enforce cross-project ownership and mandatory workflow invariants in backend validations and transactions. **Exact fields/constraints belong to the Phase 01 file**, not duplicated here.

### 8.2 Future logical entities (not yet approved for migration)

| Domain | Candidate tables / contract |
|---|---|
| Document library | `documents`, `document_imports`, `document_versions`, `document_drafts` — original asset, structured content, baseline, immutable publications |
| Changes/traceability | `document_changes`, `ticket_document_links` — ticket-scoped proposed edits and stable block references |
| Visuals/exports | `flowcharts`, `document_exports`, document assets — editable sources and derived files |
| QA | `qa_test_cases`, `qa_test_runs` — future recorded scenarios/results/evidence |
| AI | `ai_runs`, document search index records — request provenance, proposal review, retrieval references |

Future phase specifications must define columns, version and conflict handling, cross-project constraints, and migration sequencing **before** creating these tables. Do not generate the full legacy PostgreSQL schema as-is.

## 9. Phased delivery plan

| Phase | Scope | Required outcome |
|---|---|---|
| **01 — Foundation & Ticketing** | Auth, users, projects/modules, custom access, workflow versions, tickets, urgent intake, comments, attachments, reporting, history | Working secure Jira-like internal workspace using MySQL. **Detailed Phase 01 spec exists (draft for review).** |
| **02 — Process Document Foundation** | Existing DOCX import, conversion review, structured editor, drafts, baseline v1, document library/history | Coordinator can validate an imported guide and maintain an unpublished draft. **Proposed boundary.** |
| **03 — Ticket ↔ Document Lifecycle** | Highlight → ticket, ticket → document changes, stable references, conflict detection, dedicated QA evidence, review and closure-based publication | Approved, verified work updates exactly the corresponding guide version. **Proposed boundary.** |
| **04 — AI, Diagrams & Exports** | Document analysis/search, impact suggestions, draft/QA proposals, flowcharts, branded DOCX/PDF production, job monitoring | AI-assisted but human-controlled documents and generated outputs. **Proposed boundary.** |
| **05 — Collaboration & Integrations** | Advanced comments/collaboration, notifications, coverage reporting, external systems, richer templates | Scale processes and integrations only after validated need. **Proposed boundary.** |

**Phase-gate policy:** Review a phase's real end-to-end user process, confirm its missing decisions, approve acceptance criteria and schema changes, then authorize coding. Do not implement a feature merely because it appears in the roadmap.

## 10. Quality, security, and operations

- **Security:** Admin-controlled onboarding; least privilege; session security; project-scoped authorization; private files; validated uploads and downloads; no secrets in audit logs or AI prompts.
- **Data integrity:** Transactional ticket numbers, transitions, approvals, document publication, and immutable histories; safe concurrency/version checks.
- **Reliability:** Retryable background work; visible failures; independent worker health monitoring; backups and recovery checks before production deployment.
- **Performance:** Paginated/filterable ticket data; project-scoped queries; indexes driven by real query patterns; run expensive document/AI operations outside web requests.
- **Testing:** Backend permissions, state transitions, and cross-project isolation in Pest; Vue behavior in Vitest; critical login → project → ticket → QA/closure paths in Playwright. Future phases add import/editor/publishing E2E tests.
- **Observability:** Action audits, ticket activity, workflow publication logs, document-version lineage, and background job results.

## 11. Agent execution contract

1. Read `AGENTS.md`, this `docs/SDD.md`, the applicable **approved** phase file, and relevant module docs before editing.
2. Implement **only one authorized phase slice at a time**; start with Phase 01. Future-phase schemas and dependencies are not implementation requests.
3. If a requirement is ambiguous (permissions, custom workflow rules, publication gates, DOCX fidelity, or AI privacy), **report the decision needed** instead of silently choosing a new business rule.
4. Update the owning specification and add an entry under `docs/changes/` for approved process/schema/architecture changes **before implementing them**.
5. Preserve stable IDs, project scope, ticket category meaning, workflow history, audit actors, and future document-traceability contracts.
6. For each authorized slice, deliver migrations (if needed), behavior, tests, and evidence against that slice's acceptance criteria. Do not claim completion based only on passing builds.
7. No agent may silently broaden Phase 01 into document editing, AI, diagrams, full QA management, or integrations.

## 12. Decisions still to approve

**Phase 01:** Review the proposed defaults in `docs/phases/PHASE_01_FOUNDATION.md`, especially per-user permission overrides, access-manager grant limits, workflow migration/version rules, mandatory QA, urgent support intake, estimates, and attachment policy.

**Later phases:** Confirm legacy DOCX formatting needs, document granularity, comment/reviewer responsibilities, exact document-to-ticket publication gates, conflict resolution, flowchart source format, AI data residency, export templates, and whether/when real-time collaboration is needed.

**Definition of complete product:** A coordinator can manage an existing client system's requirements from **ticket or process-document entry**, developers can implement and test the change, and Astreia preserves the linked history and publishes only approved, verified process-guide changes—with reliable exports and optional AI assistance.
