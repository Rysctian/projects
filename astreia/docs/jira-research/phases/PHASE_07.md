# Phase 07 — Reporting, administration and operational readiness

Status: P — proposed, unapproved. Depends on Phases 01–06 for the complete report set; individual metrics require their owning module's historical events. Goal: explain delivery and service outcomes while operating the application reliably.

## Evidence and scope

D inspiration: [custom report series](https://support.atlassian.com/jira-service-management-cloud/docs/create-a-new-custom-report/), [administrative audit](https://support.atlassian.com/jira-cloud-administration/docs/audit-activities-in-jira-applications/), [saved filters](https://support.atlassian.com/jira-software-cloud/docs/what-is-a-saved-search/), [archive behavior](https://support.atlassian.com/jira-cloud-administration/docs/archive-a-project/). Metric definitions, deployment and recovery requirements below are P. They do not claim reproduction of Jira analytics or infrastructure.

## Functional requirements

| ID     | Expected behavior                                                                                                                                         |
| ------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P07-01 | Provide scoped dashboards with allowlisted widgets, saved filters and explicit private/project-role sharing.                                              |
| P07-02 | Configure bounded report series/windows for backlog, workload, throughput, lead/cycle time, sprint scope changes and service goal compliance.             |
| P07-03 | Show metric definition/version, units, window/timezone, freshness/as-of and missing-data state; export authorized CSV privately.                          |
| P07-04 | Expose restricted administrative audit search by actor/project/action/date; preserve ticket activity as a separate history.                               |
| P07-05 | Manage reviewed global/project settings with revision, permission, validation, effective time and safe audit.                                             |
| P07-06 | Configure retention through previewable runs; require explicit authority for destructive removal/anonymization and protect referenced history.            |
| P07-07 | Verify mail/queue/scheduler/storage health, retries, failure visibility, backup/restore and supported runtime/dependency versions.                        |
| P07-08 | Produce a deployment plan including Docker as a future option, production configuration, secrets, health checks, restore rehearsal and rollback strategy. |

## Proposed metric contracts

| Metric                | Definition and edge behavior                                                                                                                                                                                  |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Open/overdue/workload | Current visible nonterminal unresolved tickets; overdue uses project-local date. Include an unassigned bucket; do not expose hidden-ticket counts.                                                            |
| Throughput            | Count successful resolution events in the window; count ticket lifecycles, explicitly separating repeat resolutions, cancellations and duplicates. Offer unique-ticket count as a separate labeled statistic. |
| Lead time             | Creation to successful resolution per lifecycle; later reopened lifecycle starts at its reopen event. Display first-lifecycle and reopened-lifecycle definitions distinctly.                                  |
| Cycle time            | Sum active work-state intervals until outcome; excluded waiting/blocked states must be explicitly configured. Do not infer from status names.                                                                 |
| Sprint outcome        | Membership/estimates at actual start, later scope changes and completion snapshot; cancelled outcomes separated. Never infer past scope from current board filter.                                            |
| SLA compliance        | Successful eligible stopped cycles within the completion window / all eligible stopped cycles; report breached/running/cancelled separately. Zero denominator means unavailable, not 100%.                    |

## Processes and business rules

Select scoped data → validate report/window → evaluate viewer permissions → compute declared metric → show as-of/unknown states → optionally generate private export. Administrative setting changes and cleanup are separately authorized and audited. See F08 in [process flows](../PROCESS_FLOWS.md).

- A dashboard share cannot bypass project/ticket/audience restrictions. Apply authorization before aggregation; shared caches key on scope/version and reauthorize downloads. Account or membership revocation invalidates access immediately.
- Parameterize report queries with an allowlisted grammar; no user SQL/JQL execution. Bound windows/series/data volume; paginate history and stream export safely.
- Historical metrics require the relevant events; if data is missing, mark unavailable/partial rather than inventing zero. Declare how imports, archive intervals, timezone boundaries and later corrections affect each metric version.
- CSV serialization handles commas/quotes/newlines and spreadsheet formula injection safely under an explicit export policy. Export failures do not change tickets or delete committed source facts.
- Audit reads require separate administration permission. Mask token/secrets and restricted message bodies; audit actor references survive suspension. General reporting never reveals password/security/session details.
- Settings use documented default precedence and versioned changes; no arbitrary executable values. Do not enable future modules by changing a flag without their approved schema and acceptance checks.
- Retention requires approved periods, dry-run counts, legal/operational review and a recoverable execution strategy. Archive alone does not delete. Cleanup must not leave dangling parents/files or unsupported metric claims.
- Backup includes relational database, required private objects and configuration/key recovery procedures. Restore rehearsal verifies login, scoped tickets, workflow lineage, attachment retrieval and resumed worker idempotency.
- Operability is not delayed to this phase: each earlier milestone already includes authorization, failure handling and tests. This phase integrates production-readiness checks and broader reports.

## Starting schema

P: project ownership, restrictive FKs, bounded JSON settings and immutable event lineage remain mandatory.

| Tables                            | Starting data / relationship                                                        |
| --------------------------------- | ----------------------------------------------------------------------------------- |
| dashboards, dashboard_widgets     | Owner/project/sharing; allowlisted widget/position/filter                           |
| report_definitions, report_series | Metric version, timezone, bounded series/criteria and safe labels                   |
| report_runs, report_exports       | Requested-by, as-of, parameters, dataset-scope digest, state; private object/expiry |
| settings, setting_revisions       | Explicit global/project scope/key, JSON value, revision and safe actor delta        |
| retention_runs                    | Policy/cutoff snapshot, preview/result counts, actor and lifecycle                  |

Source facts remain in ticket_events, sprint_events, work_logs, sla_cycles and audit_events. Start with live aggregates; add materialized snapshots only after performance/freshness needs are demonstrated. Detailed fields: [domain model](../DOMAIN_MODEL.md#phase-07--reporting-and-operational-governance).

## Deliverables and acceptance criteria

Deliver defined dashboards/reports, private CSV, safe configuration/audit, operational runbook and tested recovery after approval. Native streaming CSV needs no export renderer; monitoring services and Docker setup require a reviewed deployment change, not this research document.

- AC01: Report aggregates/export contain exactly the viewer's permitted dataset; revoked access blocks cached/exported retrieval and private message content never appears.
- AC02: Hand-checked fixtures cover reopen, cancellation, duplicate outcomes, midnight/timezone boundaries, incomplete history and zero-denominator service reports.
- AC03: Sprint history and service cycles retain past facts after ticket edits/workflow updates; freshness/partial states are visible.
- AC04: Restricted audit access works; setting revisions reject stale edits and omit secrets. Retention preview changes no data.
- AC05: Export failure is retryable without domain changes; injection-prone CSV cells follow the approved policy.
- AC06: Backup restore rehearsal recovers scoped workflow/ticket/file history and safe worker retries; failures have named remediation and measured recovery time.
- AC07: Dependency/runtime and deployment review records approved versions, storage/queue ownership, health checks, migration/rollback procedure and restore evidence. Docker work remains separately authorized.

## Deferred work and review gate

Defer arbitrary report builders, cross-organization analytics, external warehouse/BI connectors, marketplace plugins and enterprise federation. AI application features, document automation and autonomous agents remain excluded.

Owner decisions: metric formulas/windows, scope-aware cache strategy, export policy, retention/anonymization, audit access, recovery targets, hosting and eventual containerization. Approve each before coding or deployment; this phase file grants no permission to delete data or publish infrastructure.
