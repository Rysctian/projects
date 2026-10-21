# Phase 06 — Customer requests, queues and service targets

Status: P — proposed, unapproved. Depends on Phases 01–03 and 05: accounts, ticket isolation, versioned workflows/checkpoints, typed fields and audience-safe collaboration/delivery. Phase 04 planning is optional. Goal: a customer can submit and follow a request while agents triage and resolve it against explicit service goals.

## Evidence and scope

D inspiration: [request types/categories](https://support.atlassian.com/jira-service-management-cloud/docs/manage-your-request-types-and-ticket-categories/), [queues](https://support.atlassian.com/jira-service-management-cloud/docs/edit-queues/), [request/comment API](https://developer.atlassian.com/cloud/jira/service-desk/rest/api-group-request/), [approvals](https://support.atlassian.com/jira-service-management-cloud/docs/what-are-approvals/), [SLA calendars](https://support.atlassian.com/jira-service-management-cloud/docs/set-up-sla-calendars/) and [goals](https://support.atlassian.com/jira-service-management-cloud/docs/set-up-sla-goals/). Rules below are P defaults; authenticated portal/SLA behavior was not observed.

## Functional requirements

| ID     | Expected behavior                                                                                                                                             |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P06-01 | Invite/authenticate project customers independently of staff membership; permit own requests and explicit request participants only.                          |
| P06-02 | Configure customer request types with a published allowed-field contract and one mapped internal work type; present customer-friendly status mapping.         |
| P06-03 | Create one ticket and one customer request atomically; customer cannot select hidden internal privileges, assignee, state or security IDs.                    |
| P06-04 | Let customers view their permitted request/status/public comments/attachments and reply. Let agents add public replies or internal notes explicitly.          |
| P06-05 | Configure scoped queues using structured criteria, selected columns and ordering; permit triage and eligible agent assignment through normal ticket commands. |
| P06-06 | Select named request approvers for a configured checkpoint; collect decisions without granting staff permissions or exposing internal notes.                  |
| P06-07 | Configure first-response and resolution targets with ordered match criteria and working calendars; display running/paused/stopped/breached cycles.            |
| P06-08 | Resolve/reopen requests through permitted mapped workflow transitions and customer notifications; retain prior outcomes, approvals and service cycles.        |

## Processes and business rules

Customer login → choose request type → validate allowed fields → create underlying ticket/request → evaluate queue → agent triage/reply → optional designated approval → work/resolve → customer receives visible outcome. Service timer records start/pause/resume/stop from domain events independently of display. F07 in [process flows](../PROCESS_FLOWS.md) defines key failures.

- Portal authentication and project-customer enrollment are required. No anonymous intake or organization-wide automatic request sharing initially. Changing a participant requires explicit request access and permission; membership in an unrelated customer organization is insufficient.
- A customer identity is not a staff role. If a person holds both staff/customer access, server projections still depend on the requested channel and explicit audience policy, not a hidden frontend toggle.
- Request types expose only approved fields; preserve submitted type/field schema version. Disabling a type stops new intake and retains old requests. Current ticket edit policies still apply to agents.
- Customer status labels are explicit mappings from workflow-version states. Missing mappings block publication of a portal-enabled workflow/type; never fall back to internal stage labels.
- Comments and attachment content use Phase 05 audience checks in portal reads, previews, search and notifications. Customers write public comments only; internal notes and revision history never leak to them.
- Queue membership is a query, not permission or assignment. Validate criteria and sorting; large queues paginate and counts use authorized scope. An empty queue is not evidence that a request was deleted.
- Approvers are enrolled customers or staff with explicit request/checkpoint eligibility. Snapshot named approvers for each requirement revision and visit; follow Phase 02 count/self-approval rules. Revocation requires reassignment/restart or blocks progress; old approvals never silently attach to changed requirements.
- SLA policy/calendar versions are immutable after use. First matching ordered goal wins; validate overlaps/priority ordering and a default unmatched policy. Pin target and calendar per cycle. Unlike documented Jira priority-adjustment behavior, this proposed first version freezes target until an explicit audited recalculation or a new cycle.
- Count business seconds only within named timezone intervals, excluding holidays. Define DST handling with timezone-library instants; reject overlapping/invalid working intervals. Start/pause/stop conditions are allowlisted events, not arbitrary scripts.
- First response stops only on a public agent reply, not internal notes or customer replies. Resolution stops on the approved terminal outcome. Waiting-for-customer pause is explicit; not every blocked ticket pauses a clock.
- Process repeated/out-of-order events idempotently using occurrence time and stable event identity. Track processing lag separately. A prior breach remains after reopen; create a new cycle under the selected current policy. Archive pauses active clocks with an audit event; restore resumes under the pinned calendar unless the owner approves another policy.

## Starting schema

### Proposed intake and migration compatibility

Each published request-type version defines explicit native field mappings, a required default priority, and typed defaults for required custom fields omitted from the public form. The authenticated customer is always the reporter; internal workflow/status come only from the server's binding. Publication simulates the resulting work-type create contract and rejects any required field not satisfied by a valid public input or a validated server default. Customer values cannot override hidden defaults or submit internal IDs. Acceptance fixture: a hidden required custom field without a default prevents publication; adding a valid typed default permits intake and the submitted hidden override is rejected.

Workflow/type migration preview additionally validates public status mappings against **each affected request's retained request-type version**, for both the target state and all reachable subsequent states. Reject apply if a mapping is missing or the retained contract cannot support the converted type. An administrator must explicitly version/update the request contract and map the request to that version in the same audited migration; never silently replace its submission history. Acceptance fixture: an old request migrated to a newer workflow with an unmapped review state fails before any ticket/request changes commit.

Each SLA goal selects a published calendar version explicitly; the chosen goal ID, target and calendar version are pinned in the cycle. Durable clock inputs reference a project-owned `service_clock_sources` record with unique source key, source kind, optional ticket-event/audit-event FK, occurrence time and recorded time. Ticket events, project archive/restore audits and explicit recalculation commands each produce one such record; clock events deduplicate by cycle/source/kind. This permits replay of archive pauses and recalculation without pretending every input was a ticket event. Acceptance fixture: replay archive → restore twice with delayed processing and confirm identical elapsed business time and one clock effect per source.

P: keep account identity shared but staff/customer memberships separate; apply scoped FKs to request, ticket, participant, type and checkpoint.

| Tables / alteration                                       | Starting data / relationship                                                                              |
| --------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| project_customers                                         | Unique project-user enrollment with active state                                                          |
| request_types, request_type_versions, request_type_fields | Project/type identity, immutable published work-type/field contract and version-owned allowed fields      |
| service_requests, request_participants                    | Unique ticket projection; customer, request contract version, channel and explicitly allowed participants |
| portal_status_mappings                                    | Request type version, workflow-version status, safe public label                                          |
| service_queues                                            | Criteria, columns, sort and project scope                                                                 |
| request_approvers                                         | Request/checkpoint, revision/visit, named user, eligibility state; decisions remain in ticket_approvals   |
| sla_policies, sla_policy_versions                         | Policy identity and immutable ordered goal/event rules                                                    |
| business_calendars, calendar_versions                     | Calendar identity, timezone, intervals, holidays, immutable version                                       |
| sla_cycles, sla_clock_events                              | Pinned policy/calendar/target, elapsed/business timestamps/state, idempotent event identities             |

Detailed proposed fields: [domain model](../DOMAIN_MODEL.md#phase-06--service-requests). Reuse comments, attachments, outbox, notifications and checkpoint decisions; do not duplicate ticket records or customer message stores.

Additional tables from the contracts above: `request_type_defaults` stores typed hidden-field defaults, `sla_goals` connects an ordered goal to its selected calendar version, and `service_clock_sources` supplies durable ticket/project/recalculation identities. These are part of this phase's starter schema and acceptance fixtures.

## Deliverables and acceptance criteria

Deliver a scoped authenticated portal, agent queues/public replies, named approvals and service timers with visible calculation history. Use current queue/scheduler/mail infrastructure; no new framework or native reverse-engineering dependency is required. Evaluate a calendar helper only against concrete timezone test cases.

- AC01: Customer A cannot discover customer B's request through URLs, search, counts, participants, attachments or email previews. An explicit participant receives only the approved public projection.
- AC02: Request submission rejects tampered hidden fields and creates ticket/request exactly once on retried submission. Disabled types reject new intake but preserve history.
- AC03: An internal note/private attachment remains absent from every customer channel. A public reply notifies only authorized recipients.
- AC04: A nominated approver can decide the current checkpoint without staff access; stale approvals and ineligible duplicate votes fail.
- AC05: Weekends, holidays, overnight intervals, DST, pause/resume, priority changes and late events yield expected business seconds on hand-checked fixtures.
- AC06: Internal notes do not stop first-response time. A breached resolved cycle stays breached after reopen; new-cycle lineage is visible. Archive/restore pause behavior is reproducible.
- AC07: Queue sorting/pagination and current clock state agree with scoped requests; worker lag/error is displayed rather than pretending timers are current.

## Deferred work and review gate

Defer inbound email ingestion, organization-wide customer sharing, asset/CMDB, alerting/on-call, autonomous routing, full incident/problem/change operations, complex escalation engines and integrations. The source's ITSM categories do not imply all ITSM capabilities are included here.

Owner decisions: customer enrollment/recovery, request field/version rules, sharing, public status mapping, approval eligibility, target matching/recalculation, DST/pause/archive/reopen semantics and notification text. Approve before coding. Test in an authorized disposable tenant if future research needs claims about real Jira behavior.
