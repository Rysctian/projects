# Phase 01 — Secure internal ticketing core

Status: P — proposed, unapproved. Prerequisites: none. Goal: a staff member can execute one complete, authorized ticket lifecycle in an isolated project.

## Evidence and scope

D inspiration: [account lifecycle](https://support.atlassian.com/user-management/docs/deactivate-a-managed-account/), [permission schemes](https://support.atlassian.com/jira-cloud-administration/docs/what-are-permission-schemes-in-jira/), [project roles](https://support.atlassian.com/jira/kb/grant-access-to-single-jira-project-that-uses-a-shared-permission-scheme/), [ticket outcomes](https://support.atlassian.com/jira-cloud-administration/docs/what-are-issue-statuses-priorities-and-resolutions/). These contracts were documented, not executed in a Jira tenant. All requirements below are original P decisions; this file does not compare to existing product specifications.

## Functional requirements

| ID     | Expected behavior                                                                                                                                                                                          |
| ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P01-01 | Administrator invites staff with expiring single-use setup links; sign-in/reset requires an active account. Suspend/reactivate accounts without deleting history. Public signup disabled.                  |
| P01-02 | Separate global account/project administration permissions from editable project roles. Roles grant stable capability keys; renaming them does not change access.                                          |
| P01-03 | Create project with permanent unique prefix; manage memberships, roles and optional components. Archive/restore with reason and audit.                                                                     |
| P01-04 | Create ticket with summary, description, work type, priority, reporter and optional component, assignee, minutes estimate, due date and acceptance criteria. Allocate permanent project number atomically. |
| P01-05 | Choose an assignee from active staff members with work capability in this project. Allow unassigned intake; require assignment when entering active work.                                                  |
| P01-06 | Use a seeded published workflow per project: Open → In progress → Resolved, with Resolved → Open for reopen. Initial graph is stored as data; no graph editor.                                             |
| P01-07 | Resolve with outcome and completion note; reopen with reason, preserve key/history and clear the current resolution. Distinguish successful work from cancellation/duplicate outcomes.                     |
| P01-08 | Provide paginated project list/ticket list, exact-key and summary search, filters(type, priority, state, assignee, due date), allowlisted sorting and basic open/resolved/overdue totals.                  |
| P01-09 | Persist ticket events and separate administrative audit; show a permitted ticket's activity. Reject stale concurrent edits without overwriting.                                                            |

Starter work types Task/Bug/Story and priorities Low/Normal/High/Urgent are proposed seed data, not hard-coded authorization or claims about Jira defaults. No urgent incomplete-stub exception is proposed here. Summary and description are required; criteria are optional on creation and can later become type-specific validators.

## Process and business rules

Invite → verify setup → add scoped role → create project/ticket → assign → start work → resolve → optionally reopen. See F01–F02 in [process flows](../PROCESS_FLOWS.md).

- Authorize every read/write server-side; active account + active membership + action permission is required. Global administrators use explicitly granted administration powers, not a magic role-name check. Emergency access is separately audited.
- Project managers cannot grant a capability beyond their own authority. Protect the last active global administrator and the final usable project access manager from accidental self-lockout; global recovery is explicit.
- A failed invitation delivery does not create a duplicate account or expose a setup token. Rerunning seeders does not overwrite established credentials.
- Immutable prefix and number form a human key such as HELP-00001. Database ID is a separate immutable identity. Counter/ticket/event commit together; retries use a bounded idempotency key if creation is retried automatically.
- Lock/version checks guard updates and transitions. A generic edit cannot directly set status, resolution, workflow or protected ownership.
- Resolved is terminal with non-null resolution/time; Open/In progress have no current resolution. Reopen returns to Open. A configured terminal outcome is not assumed to mean successful delivery.
- Archived project reads remain permission-scoped; default active searches omit archive. All domain writes, including administrator ticket edits, require restore first. Archiving does not revoke membership.
- Deactivated assignees remain in history but are ineligible for new assignments; flag unresolved work for reassignment. Estimates are nullable nonnegative integer minutes; zero differs from unset. Due dates use a declared project timezone for overdue calculation.

## Starting schema and relationships

Project records include an IANA timezone (proposed default UTC) for due-date interpretation. This is configuration data, not inferred from the user's browser or server timezone.

P: MySQL/InnoDB/utf8mb4, unsigned IDs, UTC microsecond timestamps, restrictive FKs and safe JSON. Add same-project composite references and indexes. Exact conventions: [domain model](../DOMAIN_MODEL.md#shared-storage-rules).

| Tables                                                                                           | Minimum data / relation                                                                              |
| ------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------- |
| users, account_invitations, sessions, password_reset_tokens                                      | Account state, setup-token hash/expiry, session invalidation                                         |
| permissions, global_user_permissions                                                             | Stable capability keys and explicit global grants                                                    |
| projects, project_members, roles, member_roles, role_permissions                                 | Project/member/role ownership; unique project-user membership and scoped joins                       |
| components, work_types, priorities, resolutions                                                  | Project-owned selectable catalogs; referenced values retired rather than deleted                     |
| workflows, workflow_versions, workflow_statuses, workflow_transitions, project_workflow_bindings | Seeded immutable published version and current project binding                                       |
| project_counters, tickets                                                                        | Project sequence; explicit workflow/status/version, requirements and nullable effort/date/assignment |
| ticket_events, audit_events                                                                      | Historical actors, before/after deltas, occurrence time and correlation                              |

## Deliverables and acceptance criteria

Deliver a usable internal workspace, migrations, realistic scoped factories/seeders and tests after authorization. Proposed stack: Laravel + Vue 3/TypeScript/Inertia + MySQL + Tailwind/shadcn-vue; Pest, Vitest, Playwright. No additional domain dependency is justified.

- AC01: Invite/setup/login works once; expired/used links and suspended users fail safely; reset does not reactivate a suspended account.
- AC02: Users from project B cannot read/search/count/edit project A tickets. Renaming a role preserves permissions; attempted grant escalation fails.
- AC03: Two concurrent creates yield different sequential project numbers; no reused key after failures; stale updates leave the newer values intact.
- AC04: Eligible worker completes Open → In progress → Resolved; missing notes/outcome or an unauthorized transition is rejected. Reopen retains key and past outcome while clearing current resolution.
- AC05: Archived project is readable only to permitted users, rejects writes and is absent from default active lists; restore re-enables normal guards.
- AC06: Totals agree with the viewer's filtered scope; key search and pagination are stable under equal sort values.

## Deferred work and review gate

Defer graph editing/checkpoints/migration to 02; hierarchy/custom fields/security levels to 03; boards/sprints to 04; comments/labels/files/notifications to 05; customers/queues/SLAs to 06; advanced reporting/operations to 07.

Owner decisions: invitation/password policy, global emergency access, initial permission catalog, project prefix limits, terminal resolutions, assignment gate and archive visibility. Required test fixture: two projects, a global administrator, two different access managers, a worker, a viewer and a suspended user. Approve this phase's rules and schema explicitly before coding.
