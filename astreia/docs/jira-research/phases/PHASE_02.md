# Phase 02 — Workflow configuration and governance

Status: P — proposed, unapproved. Depends on Phase 01's active accounts, scoped permissions, ticket locks/events and seeded versioned workflow. Goal: configure and evolve executable project workflows without corrupting existing tickets.

## Evidence and scope

D inspiration: [conditions/validators/post-functions](https://support.atlassian.com/jira-cloud-administration/docs/configure-advanced-issue-workflows/), [current Cloud editor](https://support.atlassian.com/jira-cloud-administration/docs/what-is-the-new-workflow-editor/), [approval concepts](https://support.atlassian.com/jira-service-management-cloud/docs/what-are-approvals/). Jira's current editor can update active workflows; our immutable-version policy below is a P simplification, not observed Jira storage behavior.

## Functional requirements

| ID     | Expected behavior                                                                                                                                                                                                   |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P02-01 | Copy a published workflow into a draft; create/rename/reorder states and explicit transitions. Support repeated development, review, verification, staging and pre-close checkpoints.                               |
| P02-02 | Separate status identity, display name, category(todo/in-progress/done) and terminal semantics. Status names never implement authorization or gate logic.                                                           |
| P02-03 | Configure transitions using an allowlisted rule catalog: permission/current-assignee conditions; required-field/reason/assignment validators; completion/resolution effects.                                        |
| P02-04 | Configure checkpoint approvals with eligible project capabilities, required distinct actor count, explicit reject transition and optional self-approval prohibition. No universal QA requirement unless configured. |
| P02-05 | Validate graph and rule execution before publish; display impacted project bindings. Publish immutable version atomically with actor/time.                                                                          |
| P02-06 | New tickets use the current default; existing tickets retain their version. Preview and explicitly authorize migration using a complete status mapping and per-ticket conflict checks.                              |
| P02-07 | Show ticket workflow version, current allowed transitions, checkpoint history and reasons for unavailable actions where disclosure is safe.                                                                         |

## Process

Draft → validate graph/rules → review impact → publish → use for new work. Existing work remains unchanged until a separately authorized migration. Ticket execution evaluates actor conditions, validates submitted fields, checks revision/visit approvals, then commits transition/outcome/history. Failed review follows a configured edge with reason. F02–F03 in [process flows](../PROCESS_FLOWS.md) define rejection paths.

## Business rules

- Exactly one initial nonterminal state. Every active state is reachable from initial; every nonterminal state has a path to a terminal outcome. Every edge connects states from the same version. Rework cycles are allowed; dead ends and duplicate edge keys are rejected.
- An approval-gated edge must leave an executable checkpoint. Required actor count cannot exceed the eligible distinct people at decision time; an empty eligible set blocks execution with an administrative resolution path.
- Supported rules are data, not user PHP/JavaScript/SQL. Rule definitions and IDs are immutable once published; retiring a permission/rule used by old versions requires an impact review.
- Approvals bind to requirement revision and stage visit. Requirement changes, re-entry after rework, reopen and migration invalidate affected prior approvals without deleting them. Reject decisions require a reason. Self-approval policy is explicit per checkpoint.
- Enforce mandatory gates across alternate paths; a configured shortcut cannot bypass a required checkpoint for its current revision. Deny publication when a mandatory checkpoint cannot be reached on a required completion route.
- Terminal transitions set a resolution and completion note; reopened work clears the current resolution. Outcome effects and history commit in one transaction; external effects later use Phase 05's outbox.
- Migration preserves key, original actors and old workflow references in events. Require mapping of every used state; reject incompatible terminal/resolution mappings. Invalidate approvals deliberately, record old/new bindings, and reject stale ticket versions.
- Apply one bounded migration batch atomically; record run/item status for resumability. Unprocessed work stays on its old version. No automatic rollback of later user work; compensation requires a fresh reviewed mapping.
- Draft edits/publish and binding changes serialize; published versions and states referenced by history cannot be deleted. Archived projects cannot publish or migrate until restored.

## Starting schema

### Proposed approval scope

Material edits are changes to summary, description, acceptance criteria or work type, plus any custom-field value when Phase 03 is enabled. Each increments `requirement_revision` and invalidates all approvals for the previous revision. Priority, assignee, due date, estimates, labels and comments are nonmaterial by default and do not increment it. Changing that set requires a reviewed policy change, not an ad hoc edit rule.

Within one revision, a return to an earlier work/checkpoint invalidates that checkpoint and all downstream checkpoint approvals, including approvals from a checkpoint already exited; unaffected upstream approvals remain valid. Persist invalidated checkpoint IDs and the rework visit cutoff in ticket events. Re-entering a checkpoint requires fresh votes for its new visit. Reopen or workflow migration invalidates all prior approvals.

Each checkpoint declares `mandatory_for_outcomes` (success, cancel, duplicate or other) and a stable `gate_order` within its workflow version. Publication checks that each applicable completion route crosses its mandatory gates in order; cycles remain possible but never reactivate invalidated decisions. Completion checks current-revision decisions against gate order and recorded invalidation cutoffs, not just the current status's most recent pass. A checkpoint not marked mandatory still gates its configured outgoing edge; it does not impose a universal QA rule.

Add acceptance fixtures: (a) a due-date edit after leaving an approved checkpoint preserves the pass, (b) a criteria edit at the same point invalidates it, (c) a downstream rework preserves an unaffected upstream approval, and (d) success and cancellation obey their separately configured mandatory gates.

Inherit Phase 01 MySQL conventions, composite project/version FKs, ticket revision/visit/lock columns and append-only events.

| Table                                  | Starting fields / relationship                                                                                   |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| workflow_versions/statuses/transitions | Existing graph; add draft/published/retired lifecycle and editable draft fields without changing published rows  |
| transition_rules                       | transition_id, kind, allowlisted rule_key, validated arguments JSON, evaluation order                            |
| workflow_checkpoints                   | version/status, checkpoint kind, required capability/count, reject transition and self-approval policy           |
| ticket_approvals                       | ticket/checkpoint/actor, requirement revision, visit, decision, note, decision time; unique actor vote per scope |
| workflow_migration_runs                | project, from/to versions, mapping JSON, reason, actor, run state                                                |
| workflow_migration_items               | run, ticket, expected lock version, mapped old/new states and item result                                        |

Detailed proposed fields: [domain model](../DOMAIN_MODEL.md#phase-02--workflow-rules-and-migration). Type-specific bindings arrive in 03; no shared global workflow-scheme editor here.

## Deliverables and acceptance criteria

Deliver draft configuration, validation, publication, executable checkpoints and an audited migration preview/apply process after approval. Use existing stack; no workflow-engine package or visual graph dependency is mandatory.

- AC01: Publish a workflow with Development 1 → review → Development 2 → QA → staging → closure and an explicit failed-QA rework edge.
- AC02: Reject dangling edges, unreachable states, unexecutable sign-off sources and a completion shortcut that avoids a mandatory gate; published rows remain unchanged.
- AC03: An unauthorized actor, stale lock or failed validator produces no transition/effects; repeated identical vote cannot satisfy a two-person approval.
- AC04: Requirements edit or QA re-entry makes the old pass insufficient; a new eligible approval permits progress and both decisions remain in history.
- AC05: Publishing v2 leaves v1 tickets functional; migration preview lists all unmapped states. A stale batch fails atomically and retry does not duplicate migration events.
- AC06: A resolved ticket migrated to a nonterminal stage clears resolution under explicit mapping; no incompatible completion state is silently accepted.

## Deferred work and review gate

Defer arbitrary script rules, cross-project shared schemes, external triggers and integrations. Customer approvers are Phase 06; internal approvals require no customer/notification capability. Email notices activate only with Phase 05.

Owner decisions: available rule keys, mandatory checkpoint policy, approval eligibility/self-approval, rework invalidation boundary, migration batch size and terminal mappings. Approve these plus failure fixtures before coding; graph availability does not approve later phases.
