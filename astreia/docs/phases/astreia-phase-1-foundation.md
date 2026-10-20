# Astreia — Phase 01: Foundation & Ticket Management

> **Status:** Draft for process review — implementation requires approval  
> **Version:** 0.4 · October 20, 2026  
> **Target:** `docs/phases/PHASE_01_FOUNDATION.md`  
> **Scope:** Jira-like team foundation with **configurable project access and workflows**; no document automation or AI yet.

## 1. Objective and scope

Build a central workspace where coordinators and developers can receive requests, plan and track tickets, work through project-specific approval stages, and retain a reliable activity history. Begin with one existing client system (e.g., HRIS) while supporting multiple projects.

**Phase 01 must deliver:** authentication and users; projects and modules; project teams; configurable roles and permissions; configurable ticket workflow stages and transitions; ticketing and urgent fieldwork intake; assignments, estimates, comments, attachments, labels, history, search/filters, Kanban, and basic project reporting.

**Deferred:** DOCX import, user-guide/document editor, ticket↔document links, publishing, AI/Ollama, flowcharts, dedicated QA test-case management, sprints/epics, GitHub/Sheets integration, automation-rule builder, and live collaborative editing. A `documentation` ticket category exists, but no document is created or published in this phase.

**Success:** The team can manage a request from intake through development, staging or other configured checkpoints, verification, and closure—with project-specific access and an audit trail—without depending on a spreadsheet.

## 2. Users, projects, and access control

### 2.1 Project organization

- **Project/System:** A client application with a permanent short code such as `HRIS`.
- **Module:** A functional area within a project, e.g., Leave, Attendance, Payroll. Flat modules initially; module selection on tickets is optional.
- **Project team:** Active users granted access to a project through configurable roles and/or explicitly assigned permissions.
- **Ticket:** A trackable request with a permanent project-scoped key, e.g., `HRIS-00024`.

### 2.2 Dynamic roles and permissions

**Do not hard-code Coordinator, Developer, QA, or Project Admin as authorization rules.** They may be provided as **editable starter role templates** only. Each project's authorized access manager can create, rename, and maintain project roles and assign selected permission keys.

| Concept | Requirement |
|---|---|
| **System Admin** | The **only unrestricted account type**. Has complete access across all projects and can manage users, permission catalog, project roles, and workflows. Protected against removal of the last active system admin. |
| **Project role** | Configurable name and set of permissions within one project. Users can hold multiple roles in a project. Role names never determine access by themselves. |
| **Permissions** | Explicit capabilities, e.g., `project.manage`, `members.manage`, `access.manage`, `tickets.create`, `tickets.edit`, `tickets.assign`, `tickets.transition`, `tickets.verify`, `tickets.close`, `workflows.manage`. |
| **User access overrides** | Optional per-project, per-user **allow/deny** adjustments for specific permission keys. Explicit deny takes precedence over grants, except for System Admin. |
| **Access manager** | A project member granted `access.manage` may configure project roles/member access **only within their project** and may not grant capabilities they do not possess. System Admin can manage all. |
| **Effective permission** | Active account + active project membership + combined role grants + user override, subject to project/ticket conditions. Default is **deny**. |

**Account/project process**

1. Securely bootstrap a System Admin. Disable public registration.
2. System Admin creates users and sends secure, expiring password-setup links.
3. System Admin creates the project and assigns an initial authorized project access manager.
4. Access manager adds existing users, creates/edits roles, and assigns role permissions or user-level overrides.
5. Users can perform only actions they are permitted to perform in that project. Removing access/deactivating an account takes effect without erasing history.
6. Projects and modules are archived rather than hard-deleted. Archived projects are read-only for non-System Admin accounts.

**Safety rules**

- Only System Admin can create/manage **global** accounts, alter system-admin status, manage the global permission catalog, or access every project without membership.
- A project access manager may never promote anyone to System Admin, escape project scope, or grant permissions they do not hold. Prevent removal of the final project member able to manage access, unless System Admin intervenes.
- All permission checks are server-side, including list queries, downloads, ticket actions, and workflow administration. Record role, override, and membership changes in audit history.
- Ticket assignees must be active project members. Assignment eligibility is based on an explicitly granted permission (e.g., `tickets.work`), **not a role name**.

## 3. Ticket requirements

### 3.1 Categories and priorities

| Category | Purpose |
|---|---|
| `new_feature` | New client-requested functionality. |
| `enhancement` | Revision or improvement to existing functionality. |
| `bug_fix` | Correction of existing behavior. |
| `documentation` | Track documentation work; no editor/automation yet. |
| `support` | Inquiry, fieldwork, deployment assistance, or investigation. |

Priorities: `low`, `normal` (default), `high`, `urgent`. **Urgent is a priority, not a ticket category.** Ticket categories remain fixed in Phase 01 because later document-publication rules will depend on them.

### 3.2 Ticket information

| Field / capability | Rule |
|---|---|
| Key | Permanent project code + sequential number, allocated safely under concurrency. |
| Title and description | Required for normal tickets; urgent support intake may start incomplete. |
| Project and module | Project required; optional module must belong to the project. |
| Category, priority, workflow stage | Required; stage references the project's **published workflow configuration**, not a hard-coded status enum. |
| Reporter and assignee | Track creator; assignee optional at intake, must be an eligible active project member when work begins. |
| Estimate, due date | Optional; estimate stored in minutes (`null` differs from zero). Proposed display: eight-hour workday. |
| Acceptance criteria | Required for feature/enhancement/bug tickets before entering the workflow's first **work-ready** stage. |
| Verification, completion notes | Record manual verification/sign-offs at configured checkpoints; completion notes required for closure. |
| Documentation impact | On closure, feature/enhancement requires `required` or `not_required`; the latter needs a reason. Phase 01 stores the decision only. |
| Collaboration/history | Comments, private evidence attachments, labels, field changes, transitions, verifiers, and closure/reopen history. |

### 3.3 Urgent fieldwork / quick ticket

1. A user with `tickets.create` creates `support` + `urgent` with minimal details.
2. The system immediately issues a real ticket key for commit tagging and marks the record as **incomplete intake**.
3. Reporter or another authorized editor adds the actual title/description afterward; no information is invented.
4. Incomplete intake remains at the workflow's **initial intake stage** and cannot progress or close until required information is supplied.
5. Standard project authorization, workflow checks, history, and closure rules then apply. Estimates remain optional.

## 4. Configurable ticket workflow

### 4.1 Project-defined stages and transitions

**The ticket flow is not fixed.** A project may configure as many meaningful stages as needed, including multiple development, code review, deployment, staging, testing, or approval steps. Each project starts with a default template, which an authorized user can customize.

**Illustrative workflow only (not mandatory):**

`Backlog → Ready → Development → Code Review → Staging Deployment → Staging Validation → QA → Ready to Close → Closed`

A different project might have:

`Backlog → Analysis → Development 1 → Development 2 → Internal Review → UAT → Closed`

**Workflow configuration requirements**

- Authorized members with `workflows.manage` can **add, rename, reorder, disable, and connect** stages for their project; they can configure multiple sequential development/staging checkpoints.
- Each stage has a stable key, display name, order, and **semantic type** such as `intake`, `ready`, `work`, `review`, `verification`, `pre_close`, or `closed`. Types support shared rules; names remain fully customizable.
- Define **allowed transitions** explicitly; moving a ticket by any method must use the same validated transition rules. A transition can require a permission, a reason, or a recorded manual sign-off.
- Each published workflow has exactly one **initial** stage and at least one authorized **closed** destination. Closure always requires `tickets.close`, completion notes, and applicable verification/documentation-impact checks—even if someone configures a shortcut.
- Workflows support **failure/return paths** (e.g., QA fails → Development 2), and optional **blocked**, **cancelled**, and **reopened** actions with required reasons.
- Do not let workflow edits erase stage history or strand existing tickets: changes are **drafted, validated, and published as new workflow versions**. Existing tickets retain their assigned version until an explicit, audited migration decision.
- Phase 01 is a **stage/transition configurator**, not an arbitrary automation engine. No custom scripts, triggers, external integrations, or programmable conditions.

### 4.2 Ticket execution and verification

1. Authorized user creates a ticket in the workflow's initial intake stage.
2. Requirements are clarified; category-specific acceptance criteria and required assignment are completed before the first work-ready stage.
3. Authorized assignee advances through configured work stages and supplies comments or evidence.
4. Review, staging, and/or QA checkpoints require the configured **manual sign-off** before the ticket can leave them when enabled.
5. Failed verification records findings and follows a configured return transition; previous sign-offs affected by material requirement changes must be invalidated.
6. A user with `tickets.close` may close only when all **mandatory closure checks** pass, regardless of configured stage names.
7. All transitions, approvals, reasons, and actors remain auditable.

**Minimum closure checks:** correct transition; `tickets.close` permission; required manual QA verification for new-feature/enhancement/bug tickets; no outstanding required stage sign-off; nonempty completion notes; feature/enhancement documentation-impact decision. A `required` documentation impact remains **pending for a future phase**, not published.

**Exception rules:** Blocked records why and where to resume. Cancel/reopen require a reason and preserve historical events. Reopening invalidates obsolete approvals and does not reuse the ticket key. Archived projects do not allow ordinary ticket transitions.

## 5. Collaboration and work management

- Ticket creation/editing, assignment, estimates, priority, labels, comments, private attachments, and activity/history—all governed by permissions.
- Searchable, paginated ticket list with project, module, category, stage, priority, assignee, and due-date filters; status/stage-based Kanban.
- Project reporting: open, blocked, overdue, closed, and tickets by configured stage/category; workload by assignee.
- Audit account, membership, access changes, workflow changes, and ticket decisions with actor/time and reason where relevant.
- Preserve historical records through deactivation, archiving, workflow revisions, and ticket reopening.
- File upload limits/types must be approved before rollout; downloads always require project authorization.

## 6. Starting database schema — Phase 01 (MySQL)

**Database:** MySQL (supported stable/LTS release), **InnoDB**, `utf8mb4`, Laravel migrations and foreign keys. Use `BIGINT UNSIGNED` primary/foreign keys, `DATETIME(6)` timestamps stored in UTC, `TINYINT(1)` booleans, and native **`JSON`** columns. This is a **starting schema**, not executable SQL. Standard `id`, `created_at`, and `updated_at` fields are implied unless stated.

| Table | Starting fields / purpose |
|---|---|
| `users` | Laravel user fields, `is_active` (boolean), `is_system_admin` (boolean). Only System Admin has unrestricted access. |
| `sessions`, `password_reset_tokens` | Laravel session authentication and password setup/reset. |
| `projects` | Unique immutable `code`, `name`, `description`, `status`, `created_by`, `archived_at`, nullable `current_workflow_id` (points to the selected published workflow). |
| `modules` | `project_id`, `code`, `name`, `status`; unique `(project_id, code)`. Flat modules initially. |
| `project_members` | `project_id`, `user_id`; unique `(project_id, user_id)`. Membership is distinct from role assignments. |
| `permissions` | Unique stable `key`, `description`, `scope` (`project`/`system`). Project roles may receive **only project-scoped** capabilities. |
| `roles` | `project_id`, `name`, `description`, `is_active`; unique `(project_id, name)`. Roles are configurable. |
| `role_permissions` | `role_id`, `permission_id`; unique pair. |
| `project_member_roles` | `project_member_id`, `role_id`; unique pair. Role and member must belong to the same project. |
| `project_member_permission_overrides` | `project_member_id`, `permission_id`, `effect` (`allow`/`deny`); unique member/permission. Explicit deny wins except for System Admin. |
| `project_workflows` | `project_id`, `version_number` (**INT UNSIGNED**), `state` (`draft`/`published`/`retired`), `published_at`; unique `(project_id, version_number)`. Keep prior published versions immutable. |
| `workflow_stages` | `workflow_id`, `key`, `name`, `type`, `sort_order`, `is_initial`, `is_active`; unique `(workflow_id, key)`. |
| `workflow_transitions` | `workflow_id`, `from_stage_id`, `to_stage_id`, nullable `required_permission_id`, `requires_reason`, `requires_signoff`; unique `(workflow_id, from_stage_id, to_stage_id)`. |
| `project_ticket_counters` | `project_id` (primary/foreign key), `next_number` (**BIGINT UNSIGNED**); row-locked ticket numbering. |
| `tickets` | `project_id`, nullable `module_id`, `number` (**BIGINT UNSIGNED**), `workflow_id`, `stage_id`, `revision_number` (**INT UNSIGNED**, default 1), `title`, `description`, `category`, `priority`, `is_intake_stub`, `created_by`, nullable `assigned_to`, nullable `estimate_minutes` (**INT UNSIGNED**), `due_date`, `acceptance_criteria` (**JSON**), `completion_notes`, `documentation_impact`, `documentation_impact_note`, `blocked_from_stage_id`, `closed_by`, `closed_at`; unique `(project_id, number)`. |
| `ticket_stage_signoffs` | `ticket_id`, `stage_id`, `actor_id`, `kind` (review/QA/staging/resolution), `result` (pass/fail), `note`, `verified_revision` (**INT UNSIGNED**), `created_at`. Sign-offs only count for the matching ticket revision. |
| `ticket_comments` | `ticket_id`, `author_id`, `body`, timestamps. |
| `ticket_activities` | `ticket_id`, `actor_id`, `activity_type`, `payload` (**JSON**), `created_at`; immutable stage/action history. |
| `ticket_attachments` | `ticket_id`, `uploaded_by`, private `storage_disk`/`storage_path`, `original_filename`, `mime_type`, `size_bytes`. |
| `project_labels` / `ticket_labels` | Project-scoped labels; unique `(project_id, name)` and `(ticket_id, label_id)` respectively. |
| `audit_logs` | Nullable `project_id`, nullable `actor_id`, `entity_type`, `entity_id`, `action`, `before_json` (**JSON**), `after_json` (**JSON**), `created_at`. No passwords or secrets. |

**MySQL data integrity and indexing**

- Enforce foreign keys, unique constraints, project ownership, and server-side authorization. Related members, roles, modules, ticket labels, workflow versions, stages, transitions, and assignees must resolve to the **same project**. Use composite foreign keys where practical, plus transactional business validation.
- `projects.current_workflow_id` identifies the **single active published workflow**. Publish a new version and switch this pointer in one transaction after validation. MySQL does **not** provide PostgreSQL-style partial unique indexes, so do not design around them.
- A ticket's `stage_id` must belong to its referenced `workflow_id`; transition endpoints must belong to that same workflow version. Keep historical versions and used stages permanently. Existing tickets remain on their assigned workflow version until an explicit, audited migration.
- Store `acceptance_criteria`, `ticket_activities.payload`, and audit before/after snapshots in MySQL `JSON` columns (**not `JSONB`**). Use normal relational columns for fields that need frequent filtering or indexing; do not assume PostgreSQL JSON operators or GIN indexes.
- Allocate project ticket numbers by locking the project's counter row in an InnoDB transaction. Never use unprotected `MAX(number) + 1`; do not reuse cancelled ticket numbers.
- Increment `tickets.revision_number` when a material requirement changes. Ignore sign-offs tied to previous revisions; record changes, sign-offs, transitions, and closure atomically where required.
- Index at minimum: `tickets(project_id, stage_id)`, `tickets(project_id, assigned_to)`, `tickets(project_id, due_date)`, `project_workflows(project_id, version_number)`, `ticket_activities(ticket_id, created_at)`, and membership/permission lookup keys. Check actual query plans before adding further indexes.
- Use MySQL-supported constraints plus Laravel validation and services for cross-row rules (such as exactly one initial stage, mandatory closure gates, and no cross-project role assignments). Avoid reliance on database features that differ from MySQL.
- **Not created in Phase 01:** document libraries/drafts/versions, ticket–document links, embeddings/AI runs, flowcharts, DOCX exports, and dedicated QA test-case/run tables.

## 7. Technology stack and dependencies

Use latest **stable, mutually compatible** releases at bootstrap; verify against official starter-kit support.

| Layer | Choice |
|---|---|
| Application | **Laravel 13** modular monolith |
| Frontend | **Vue 3** + TypeScript + Composition API |
| UI foundation | Tailwind CSS 4 + shadcn-vue |
| Build and database | Vite + **MySQL (InnoDB, utf8mb4)** |
| Tests | Pest, Vitest, Playwright |

**Dependencies needed now:** Laravel Vue/Inertia starter, **MySQL server + PHP `pdo_mysql` extension**, Tailwind/shadcn-vue (if absent), test tooling, development mail transport/mail catcher, and private attachment storage. If using a roles package, verify support for project-bound roles, membership-scoped grants, and deny overrides **before** choosing it; do not force a package's defaults onto the approved access model.


## 8. Phase 01 delivery slices and acceptance

| Slice | Deliverables | Exit demonstration |
|---|---|---|
| **1.1 — Identity** | Login/logout, setup/reset, System Admin, active/inactive users. | Only active users authenticate; only System Admin has universal access. |
| **1.2 — Projects & permissions** | Projects, modules, members, configurable roles, permission grants/overrides, security and audit. | Create a custom role with limited actions; another project's records remain inaccessible. |
| **1.3 — Configurable workflows** | Project workflow templates, stages, transitions, manual gates, version publishing. | Add `Development 2` and `Staging Validation`; new tickets follow the configured transitions; prior tickets/history stay valid. |
| **1.4 — Ticket core** | Ticket CRUD, five categories, assignment, priority, estimate, unique keys, urgent intake. | Regular and urgent tickets create correctly; no duplicate numbers in concurrent requests. |
| **1.5 — Lifecycle & collaboration** | Transition enforcement, block/return/reopen, stage verification, close, comments, attachments, activity. | Ticket completes configured development/staging/QA checkpoints; unauthorized skips/closures fail. |
| **1.6 — Work tracking & sign-off** | Search/filters, Kanban, reporting, security, end-to-end tests and handoff. | Team operates a real project end-to-end; audit/access and workflow acceptance tests pass. |

**Required acceptance scenarios**

- System Admin manages all projects; a non-admin with custom project permissions can act only within their authorized project/actions.
- A project access manager creates an `Implementation Reviewer` role, grants selected permissions, and assigns it without changing code.
- A project changes its workflow from `Development → QA` to `Development → Code Review → Staging → QA`; tickets using an older published version retain their history.
- A user cannot jump directly from Development to Closed or skip required verification, even through a manually crafted request.
- A failed staging/QA check records findings and returns to the configured work stage; stale approvals cannot be reused.
- Urgent support intake receives a real project ticket key immediately, but cannot progress while incomplete.
- Cross-project ticket, member, workflow, comment, and attachment access is rejected.
- Ticket history survives account deactivation, workflow revisions, reopening, and archiving.
- Closing with `documentation_impact = required` records pending documentation only; no document is published in this phase.

**Phase 01 deliverable:** A usable internal ticketing application, approved **MySQL** database migrations, configurable project permissions and workflows, automated acceptance tests, and an implementation handoff record.

## 9. Decisions to confirm before authorizing agents

These are proposed design defaults; confirm them before implementation rather than letting an agent silently choose business policy.

| Decision | Proposed default |
|---|---|
| Access management | System Admin manages global accounts and permission catalog; project access manager may configure access only within permissions already granted. |
| Per-user permission overrides | Support `allow` / `deny`; deny wins except for System Admin. |
| Role templates | Provide sample roles for coordinator/developer/QA/viewer; none are hard-coded authorization identities. |
| Workflow scope | One currently published workflow version per project; all ticket categories use it initially. |
| Existing tickets after workflow revision | Remain on original version until explicit migration, with history preserved. |
| Configurable conditions | Allowed transitions, required permission, reason, manual sign-off; no custom scripting or workflow automation engine. |
| Verification | Feature/enhancement/bug tickets require a valid manual QA pass before closure. Extra stages may request additional sign-offs. |
| Quick urgent support | Incomplete `support` + `urgent` ticket key available immediately; details required before leaving intake. |
| Estimates and attachments | Estimate optional, eight-hour display workday; determine upload limits and file types before implementation. |

**Agent instruction:** This file defines **business processes and deliverables**, not UI layout or code organization. Implement only approved slices, preserve the configurable access/workflow domain model, and document any proposed change before changing migrations or workflow rules.

**Phase 01 handoff:** Preserve stable project/ticket IDs, actors, permissions, workflow-version references, history, and documentation-impact decisions. Subsequent phases will add process documents, user guides, bidirectional ticket↔document linking, AI assistance, and publishing gates.
