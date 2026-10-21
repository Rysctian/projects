# Phase 03 — Structured work, teams and access refinement

Status: P — proposed, unapproved. Depends on Phases 01–02 for scoped ticket identity, locks, catalogs and immutable workflows. Goal: represent linked/hierarchical work with validated fields and explicit team responsibility.

## Evidence and scope

D inspiration: [subtasks and visibility](https://support.atlassian.com/jira-software-cloud/docs/create-a-work-item-and-a-subtask/), [configuration/components/versions](https://support.atlassian.com/jira-cloud-administration/docs/configure-a-space/), [teams](https://support.atlassian.com/platform-experiences/docs/using-atlassian-teams-in-jira-projects/), [security levels](https://support.atlassian.com/jira-cloud-administration/docs/grant-users-access-to-security-levels-in-a-work-item-security-scheme/). The schema and defaults below are original P choices; no tenant behavior was executed.

## Functional requirements

| ID     | Expected behavior                                                                                                                                                              |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| P03-01 | Configure project work types with epic/standard/subtask levels. Allow epic → standard → subtask only; each child has at most one parent.                                       |
| P03-02 | Add directional blocks/duplicates/relates links with visible source/target. Represent dependency and hierarchy separately.                                                     |
| P03-03 | Create teams, team membership and project-team association. Permit one team and one accountable individual assignee per ticket.                                                |
| P03-04 | Configure text, decimal, date, boolean and single-select custom fields, requiredness and work-type applicability. Enforce the same rules at create/edit/transition boundaries. |
| P03-05 | Add multiple project components, release versions and affected/fix-version relations. Retain retired catalog values historically.                                              |
| P03-06 | Map work types to a published workflow with project default fallback. Type conversion validates fields, hierarchy, current state and workflow mapping.                         |
| P03-07 | Add optional security levels restricting project-ticket visibility to allowed project roles. Apply restrictions to history, counts and every downstream consumer.              |

## Processes and business rules

Create/change parent → authorize both ends → check same project/level/cycle → commit relationship and history. Assign team → verify active project-team link → verify assignee remains an eligible active project member. Type conversion → preview incompatible fields/states → supply an approved mapping → commit type, fields and workflow together. See F03–F04 in [process flows](../PROCESS_FLOWS.md).

- Reject self-parenting, cycles, wrong levels and hidden parents. Parent removal does not delete child work. No cross-project parent/link/move in this phase.
- Open children block parent completion by default; an optional permissioned override requires a reason and an approved policy. A `blocks` edge is advisory unless the selected workflow explicitly enforces it.
- Duplicate completion requires a visible, different target ticket linked as duplicate. Resolving a duplicate does not resolve the target.
- Team membership is organizational context, not project authorization. Removing a team/member does not delete history; flag incompatible active assignments for reassignment.
- Field type is immutable after use. Retiring a field/option stops new selection, preserves old display and must not silently invalidate existing tickets. Changing requiredness does not fabricate values; block the next governed action with specific errors until completed.
- Changing workflow/type is a versioned migration, not a direct edit to `workflow_version_id`. New bindings affect new tickets. Reuse Phase 02 migration rules for existing tickets.
- Ticket security intersects project permission. Subtasks inherit parent restriction and cannot widen access. Do not reveal hidden parent/link summaries or counts. A global administration permission alone does not silently disclose ticket content; any emergency access is explicit and audited.
- A release is not a sprint. Releasing a version does not silently close its tickets; prevent or confirm unresolved work under the approved release policy.

## Starting schema

P: retain MySQL project-scoped composite FKs, lock versions, UTC events and restrictive deletion.

| Tables / alteration                                                | Starting data / relationship                                                                                            |
| ------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| teams, team_members, project_teams                                 | Team identity/state; unique team-user and project-team joins                                                            |
| work_types/tickets additions                                       | hierarchy_level; parent_ticket_id?, team_id?; scoped parent and cycle validation                                        |
| link_types, ticket_links                                           | Direction labels, type, source/target scoped ticket IDs; unique edges                                                   |
| field_definitions, field_options, type_fields, ticket_field_values | Typed definitions/options, applicable/required fields and one validated typed value per ticket-field                    |
| ticket_components                                                  | Backfill Phase 01 component_id, then use multiple scoped component joins; remove old column only after verified cutover |
| release_versions, ticket_releases                                  | Named release lifecycle/date and ticket relationship kind                                                               |
| project_workflow_bindings addition                                 | Optional work_type_id; one default and one override per type                                                            |
| security_levels, security_level_roles, tickets.security_level_id   | Project-specific restricted access rules                                                                                |

Detailed proposed fields: [domain model](../DOMAIN_MODEL.md#phase-03--structured-work-teams-and-visibility). For default bindings, MySQL nullable uniqueness alone is insufficient: use an explicit default sentinel/key or equivalent serialized constraint design before migration approval.

## Deliverables and acceptance criteria

Deliver typed/hierarchical ticket operations, field configuration, team/release assignment and security-aware retrieval. No additional library is required beyond the established stack.

- AC01: Epic, standard ticket and subtask can be independently assigned/tracked; self/cyclic/wrong-level/cross-project parent changes fail atomically.
- AC02: Field values reject incompatible types/options; retired values remain historically readable; a type conversion cannot leave invalid required fields or workflow status.
- AC03: A team member without project access gains none; inactive/non-project assignees are rejected even when team-selected.
- AC04: A restricted ticket is absent from unauthorized list/search/count/link/parent responses and all enabled collaboration/reporting paths.
- AC05: Publishing a type-specific binding leaves existing work unchanged; explicit mapping produces an auditable conversion.
- AC06: Parent closure with open children is blocked under the default policy. Duplicate resolution requires an authorized visible target.

## Deferred work and review gate

Defer arbitrary hierarchy levels, multi-select/rich formula fields, cross-project moves, shared schemes and portfolio planning. No Jira-compatible query or schema is promised.

Owner decisions: parent matrix, duplicate/block semantics, closure override, field limits, release completion, security inheritance and global emergency access. Approve these decisions before coding; regression fixtures must include a hidden parent, retired option, suspended teammate and v1/v2 workflow conversion.
