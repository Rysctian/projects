# Phase 04 — Backlog, boards, sprints and tracking

Status: P — proposed, unapproved. Depends on Phases 01–03: tickets, state transitions, work types, visibility and hierarchy. Goal: plan work and retain enough scope history to explain delivery.

## Evidence and scope

D inspiration: [board columns](https://support.atlassian.com/jira-software-cloud/docs/configure-columns/), [team-managed sprints](https://support.atlassian.com/jira-software-cloud/docs/enable-sprints/), [saved filters](https://support.atlassian.com/jira-software-cloud/docs/what-is-a-saved-search/), [time logs](https://support.atlassian.com/jira-software-cloud/docs/log-time-on-an-issue/). Our project-scoped boards, terminal completion policy and ranking are P choices, not Jira internals.

## Functional requirements

| ID     | Expected behavior                                                                                                                                             |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P04-01 | Save owned structured filters with private/project-role sharing, selected columns and allowlisted sort. Evaluate under each viewer's current authorization.   |
| P04-02 | Configure project-local Kanban or Scrum boards mapping one or more workflow states to ordered columns; show unmapped-work diagnostics to authorized managers. |
| P04-03 | Rank tickets in a backlog independently of priority and status. Handle concurrent reorder/rebalance safely.                                                   |
| P04-04 | Create future sprint with name, goal, planned dates; choose scope; start, track and complete. One active sprint per board initially.                          |
| P04-05 | Carry unfinished work explicitly to backlog or a future sprint; retain completed sprint membership and all scope changes.                                     |
| P04-06 | Show workload by assignee/team with unassigned bucket, due dates, minutes estimates and optional story points; keep units distinct.                           |
| P04-07 | Record positive-minute work logs with author/time; permissioned edits/voids retain revision history.                                                          |
| P04-08 | Show basic sprint scope/completion and current board totals with named definitions. Phase 07 adds general dashboards, trends and exports.                     |

## Processes and business rules

Filter → authorize result → configure board → rank backlog → select future sprint → start with scope snapshot → perform work using Phase 02 transitions → complete and carry unfinished scope. F05 in [process flows](../PROCESS_FLOWS.md) describes ambiguous drops and missing cards.

- Board/filter sharing grants no ticket permission. Default boards include all active workflow versions' relevant states; a version change cannot silently orphan ticket visibility.
- A column is presentation, not a state. If multiple valid target states exist, ask the user to select a transition. If none exist, reject drop without rank/state side effects. Stale drops retain newer server state.
- Unmapped tickets remain discoverable through authorized search; show why a card is excluded when authorized. Card pagination and totals cover the full scoped query, not only loaded cards.
- Backlog order is board-local; serialize rank changes and use stable tie-breaker IDs. Start with integer ranks and bounded transactional rebalance; do not claim reproduction of Jira's ranking algorithm.
- Sprint lifecycle is future → active → closed. Starting requires valid start/end, nonempty eligible scope and manage-sprint permission. Snapshot actual start separately from planned start.
- One ticket can belong to at most one current future/active sprint; historical membership remains many-to-many. This deliberately avoids cross-board planning ambiguity. Carry-over records removal/addition without rewriting the closed sprint.
- Completion uses terminal-category status plus explicit resolution, not column position. Capture each ticket's completion at sprint close; later reopen does not rewrite that result. Cancelled/duplicate outcomes are reported separately from successful delivery.
- Story points are not hours; do not sum them across differently configured boards. Preserve baseline estimates plus later changes. Work logs are actual effort, not cycle time or proof of productivity.
- WIP limits are advisory indicators in this phase. A strict cap is a separate workflow policy. Archived projects reject rank, scope and work-log changes.

## Starting schema

### Proposed authority for sprint-wide commands

Start, complete and bulk carry-over require manage-sprint permission plus visibility and scheduling authority over every affected member ticket, checked in one serialized command. A missing permission rejects the whole command with a generic authorization failure; do not return hidden member IDs, summaries or hidden-ticket counts. Authorized managers must arrange an eligible planner or change scope through a separately authorized operation. Read-only board totals still use only visible tickets. Acceptance fixture: a manager who cannot view one unfinished member cannot complete or partially carry over that sprint; no hidden details are disclosed and no membership changes commit.

P: inherit same-project composite FKs, visibility predicates and event/lock conventions.

| Tables                                       | Starting data / relationship                                                          |
| -------------------------------------------- | ------------------------------------------------------------------------------------- |
| saved_filters, filter_shares                 | Owner, criteria/sort JSON, project and explicit role shares                           |
| boards, board_columns, board_column_statuses | Kind/unit/filter, ordered columns and version-aware state mappings                    |
| backlog_ranks                                | Unique board-ticket rank, transactional reorder                                       |
| sprints                                      | Board/project, state, goal, planned/actual dates, lock version                        |
| sprint_tickets, sprint_events                | Historical membership intervals, start/close estimate/outcome snapshots, scope events |
| work_logs, work_log_revisions                | Ticket/user, minutes, occurrence, revision/void metadata and append-only prior values |
| tickets.story_points                         | Nullable nonnegative decimal effort, separate from minutes                            |

Detailed proposed fields: [domain model](../DOMAIN_MODEL.md#phase-04--planning). Keep unique active-sprint and current-membership constraints explicit in the approved design; do not assume a MySQL partial index.

## Deliverables and acceptance criteria

Deliver usable backlog and board selection, a complete sprint lifecycle, logs and scoped planning summaries. Use MySQL query/rank operations; full JQL, an external search engine and realtime synchronization are unnecessary.

- AC01: A shared filter exposes only the viewer's permitted tickets. Unknown operators/sort columns are rejected.
- AC02: Multiple statuses can share one column; ambiguous moves require a selected transition and failed moves change nothing. Old-version tickets remain mapped or diagnosable.
- AC03: Concurrent ranks remain unique and stable; large columns paginate independently and counts include unloaded authorized cards.
- AC04: A second active sprint on the same board and current membership in two sprints are rejected. Completing a sprint requires an explicit destination for unfinished work.
- AC05: Added/removed work and estimate changes after start remain traceable. A later reopen does not rewrite the closed sprint snapshot.
- AC06: Work logs distinguish actual minutes, estimates and points; unauthorized edits fail and voided entries retain history.

## Deferred work and review gate

Defer cross-project boards, multiple active sprints per board, full JQL, advanced roadmaps, predictive estimates, automated staffing and strict WIP enforcement.

Owner decisions: ranking scope, estimation unit, completion definition, membership uniqueness, carry-over destinations, work-log editing and metric baseline. Approve before coding. Fixtures need repeated states across workflow versions, hidden tickets, an ambiguous column, carried-over work and a reopened closed-sprint ticket.
