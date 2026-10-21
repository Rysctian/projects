# Jira-inspired ticketing research

Status: independent research draft; no development phase is approved. Researched 2026-10-21, Asia/Singapore.

## Purpose and reading order

Design an original, standalone project and ticket management application from accessible Jira Cloud and Jira Service Management (JSM) behavior. This directory implements the research request in `docs/prompt-rea.md`. It neither amends nor compares against existing product specifications. All schemas and rules labeled **P** are proposals for review, not discoveries about Atlassian's internals.

1. [Feature inventory](FEATURE_INVENTORY.md): evidence, responsibilities, dependencies, exceptions.
2. [Process flows](PROCESS_FLOWS.md): end-to-end behavior and failure paths.
3. [Domain model](DOMAIN_MODEL.md): glossary, proposed MySQL model and invariants.
4. [Development roadmap](DEVELOPMENT_ROADMAP.md): independently reviewable milestones.
5. [Phase 01](phases/PHASE_01.md) through [Phase 07](phases/PHASE_07.md): standalone requirements and acceptance criteria.

## Evidence boundaries

| Label | Meaning                                       | Allowed conclusion                                                             |
| ----- | --------------------------------------------- | ------------------------------------------------------------------------------ |
| O     | REA-observed public browser content           | What appeared during this exact public-page capture                            |
| D     | Official Cloud documentation or API reference | What Atlassian documents, subject to product, plan and configuration           |
| P     | Proposed original design / simplification     | A recommendation requiring explicit review                                     |
| U     | Unverified                                    | Requires an authorized tenant, specific plan/configuration or further evidence |

REA server 6.7.0 and skill version 42 were available. `capture_browser_scenario` launched existing Chromium 156.0.8078.4 in a provider-owned temporary profile, navigated to the supplied URL, waited 1.5 seconds and captured URL, DOM and accessibility text. No credentials, form submissions, sign-ups or tenant changes were performed. Cleanup reported `terminated-owned-process`.

**O-01 evidence:** `ev_7eda26f133daefe3f584956aa864c2d85825ec20b912ba3c6d0a6e57976d6195`; scenario digest `6a3daf285445abbbd9b8e397752707d4ff7cb7038c10b09fc319f8d613653743`; DOM digest `707c91ab464b59ef085a0dcb0ac4e55c47caf986f002df95ff2a723d43ee62e8`; accessibility digest `bfdbe11afde04e9c0fb3290bf982d163117e22fc330f31aaac1cc8034b404bc3`. See the [evidence receipt](evidence/rea-public-page.json), an analyst summary of selected facts, not the full canonical REA record. The full capture remains in the active REA ledger; this receipt does not duplicate the site's scripts or assets.

Observed: a sign-in link, work-email signup form, demo link, and a service-management preview with IT support, IT operations, Engineering and HR tabs; the default preview displayed a help center. These are public marketing-page controls, not verified tenant workflows. Advertised AI features are excluded from this blueprint.

No authenticated Jira tenant, customer portal, browser session or API credentials were supplied. Account provisioning, ticket edits, transitions, permission enforcement, queues, sprints, notifications, reports and API responses were **not executed**. Jira source code, production database schema, hidden validation, indexing internals and exact transaction behavior remain unknown. The documentation investigation covers every requested module; it is not an exhaustive reverse engineering of the entire SaaS product. Cloud sources using the newer terms **space** and **work item** are normalized here to **project** and **ticket**. Data Center-only material is excluded.

## System purpose and module boundaries

**D:** Jira combines configurable work records with access rules and workflows. JSM adds a customer-facing request layer and service operations. These must not be collapsed into identical permissions or assumed to share every setting.

**P:** One organization per deployment; projects own tickets and configurations. Begin with internal staff. Add customer access only once comment visibility, notification and download boundaries are explicit. One ticket is the source of truth for its current state; boards and queues are projections, not duplicate ticket stores.

| Module                   | Owns                                                         | Depends on                                      |
| ------------------------ | ------------------------------------------------------------ | ----------------------------------------------- |
| Identity/access          | Accounts, project membership, roles, permission checks       | None                                            |
| Projects                 | Scope, membership context, components and lifecycle          | Identity/access                                 |
| Tickets                  | Stable keys, requirements, assignment, resolution, history   | Projects, workflow                              |
| Workflow                 | Versioned states, allowed transitions, validation, approvals | Access; tickets for execution                   |
| Structured work          | Types, custom fields, hierarchy, links, teams                | Ticket identity and access                      |
| Planning                 | Saved filters, board mappings, ranking, sprints              | Tickets, states, hierarchy                      |
| Collaboration            | Comments, attachments, mentions, subscriptions, delivery     | Ticket visibility and transactional history     |
| Service requests         | Customer portal, request types, queues, SLAs                 | Tickets, access, workflow, collaboration        |
| Administration/reporting | Configuration, audit, dashboards, operational reliability    | Events and permissions from each enabled module |

## Technology direction (P)

Laravel modular monolith, Vue 3 + TypeScript, Inertia, MySQL, Tailwind CSS + shadcn-vue; Pest, Vitest and Playwright. Laravel's current stable major is 13 ([official releases](https://laravel.com/framework/docs/releases)); select supported compatible stable patch versions when a phase is approved, pin lockfiles and validate the runtime matrix. This research does not upgrade dependencies. Use conventional controllers, policies and focused workflow services, not separate microservices or a generic script engine.

Use database-backed queues initially for after-commit notifications and service calculations; Redis is optional after measured need. Private filesystem storage first, object storage when deployment requires it. Basic relational queries and MySQL full-text search precede a separate search engine. Docker is a future deployment direction only: no installation or containerization is authorized here. No additional packages are required to read this blueprint.

Exclude AI application features, document automation, autonomous agents, copied branding/assets/code, asset/CMDB management, on-call operations, marketplace extensions and full Jira compatibility. Preserve original implementation freedom.

## Source register

Every reference below was consulted on 2026-10-21. Links establish documented contracts, not actual tenant observations. Avoid treating examples as universal defaults or combining team-managed and company-managed behavior. Subscription availability and rolling interface changes must be checked for a selected tenant.

| ID  | Official source                                                                                                                                                                 | Research use                                      |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| D01 | [JSM public reference](https://www.atlassian.com/software/jira/service-management)                                                                                              | Public product context only                       |
| D02 | [Managed-account deactivation](https://support.atlassian.com/user-management/docs/deactivate-a-managed-account/)                                                                | Account lifecycle                                 |
| D03 | [Managed accounts versus users](https://support.atlassian.com/atlassian-cloud/kb/atlassian-administration-what-is-the-difference-between-users-page-and-managed-accounts-page/) | Account ownership versus app access               |
| D04 | [Permission schemes](https://support.atlassian.com/jira-cloud-administration/docs/what-are-permission-schemes-in-jira/)                                                         | Global/project distinction and sharing            |
| D05 | [Project role isolation](https://support.atlassian.com/jira/kb/grant-access-to-single-jira-project-that-uses-a-shared-permission-scheme/)                                       | Role membership scope                             |
| D06 | [Work-item security levels](https://support.atlassian.com/jira-cloud-administration/docs/grant-users-access-to-security-levels-in-a-work-item-security-scheme/)                 | Additional visibility restriction                 |
| D07 | [Project configuration](https://support.atlassian.com/jira-cloud-administration/docs/configure-a-space/)                                                                        | Components, versions and settings                 |
| D08 | [Project archive](https://support.atlassian.com/jira-cloud-administration/docs/archive-a-project/)                                                                              | Retention, read-only behavior, access caveats     |
| D09 | [Create work/subtasks](https://support.atlassian.com/jira-software-cloud/docs/create-a-work-item-and-a-subtask/)                                                                | Intake and child work                             |
| D10 | [Status, priority and resolution](https://support.atlassian.com/jira-cloud-administration/docs/what-are-issue-statuses-priorities-and-resolutions/)                             | Separate lifecycle concepts                       |
| D11 | [Advanced workflow rules](https://support.atlassian.com/jira-cloud-administration/docs/configure-advanced-issue-workflows/)                                                     | Conditions, validators and post-functions         |
| D12 | [Workflow editor](https://support.atlassian.com/jira-cloud-administration/docs/what-is-the-new-workflow-editor/)                                                                | Publishing and status replacement                 |
| D13 | [Board columns](https://support.atlassian.com/jira-software-cloud/docs/configure-columns/)                                                                                      | Mapping versus workflow status                    |
| D14 | [Sprints](https://support.atlassian.com/jira-software-cloud/docs/enable-sprints/)                                                                                               | Team-managed sprint behavior                      |
| D15 | [Saved search](https://support.atlassian.com/jira-software-cloud/docs/what-is-a-saved-search/)                                                                                  | Filter ownership and sharing                      |
| D16 | [JSM comments](https://support.atlassian.com/jira-service-management-cloud/docs/talk-to-the-customer-or-team-members-from-the-new-issue-view/)                                  | Internal note/customer reply                      |
| D17 | [Watching and mentions](https://support.atlassian.com/jira-service-management-cloud/docs/watch-and-share-issues-from-the-new-issue-view/)                                       | Collaboration recipients                          |
| D18 | [Customer/internal notifications](https://support.atlassian.com/jira-service-management-cloud/docs/what-notifications-do-my-customers-and-service-desk-team-receive/)           | Separate delivery audiences                       |
| D19 | [Queue configuration](https://support.atlassian.com/jira-service-management-cloud/docs/edit-queues/)                                                                            | Filtering and presentation                        |
| D20 | [Request types/categories](https://support.atlassian.com/jira-service-management-cloud/docs/manage-your-request-types-and-ticket-categories/)                                   | Portal intake classification                      |
| D21 | [SLA calendars](https://support.atlassian.com/jira-service-management-cloud/docs/set-up-sla-calendars/)                                                                         | Working hours and timezone                        |
| D22 | [SLA goals](https://support.atlassian.com/jira-service-management-cloud/docs/set-up-sla-goals/)                                                                                 | Matching and targets                              |
| D23 | [Custom reports](https://support.atlassian.com/jira-service-management-cloud/docs/create-a-new-custom-report/)                                                                  | Filtered report series                            |
| D24 | [Audit activities](https://support.atlassian.com/jira-cloud-administration/docs/audit-activities-in-jira-applications/)                                                         | Configuration audit versus ticket history         |
| D25 | [Platform REST overview](https://developer.atlassian.com/cloud/jira/platform/rest/v3/intro/)                                                                                    | Authentication, paging, rich text                 |
| D26 | [Issue API](https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issues/)                                                                                      | Create/edit/assignment/transitions/changelog      |
| D27 | [Attachment API](https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-attachments/)                                                                      | File resources                                    |
| D28 | [JSM Request API](https://developer.atlassian.com/cloud/jira/service-desk/rest/api-group-request/)                                                                              | Portal comments, participants and visibility      |
| D29 | [Sprint API](https://developer.atlassian.com/cloud/jira/software/rest/api-group-sprint/)                                                                                        | Sprint state changes                              |
| D30 | [Time logging](https://support.atlassian.com/jira-software-cloud/docs/log-time-on-an-issue/)                                                                                    | Estimate/time-log distinction                     |
| D31 | [Approvals](https://support.atlassian.com/jira-service-management-cloud/docs/what-are-approvals/)                                                                               | Named approval steps                              |
| D32 | [Teams](https://support.atlassian.com/platform-experiences/docs/using-atlassian-teams-in-jira-projects/)                                                                        | Team association versus individual work ownership |
| D33 | [Search API](https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/)                                                                               | Enhanced query search                             |

## Remaining investigation and review

| Gap                                   | Why it matters                                           | Follow-up evidence                                                           |
| ------------------------------------- | -------------------------------------------------------- | ---------------------------------------------------------------------------- |
| U01: tenant plan and management style | Roles, rules and planning capabilities vary              | Authorized test tenant and configuration inventory                           |
| U02: real permission/error behavior   | Documentation cannot prove a given configuration is safe | Member/outsider/customer test accounts; read, transition and download checks |
| U03: workflow updates under load      | No observed locking or migration implementation          | Controlled disposable-ticket migration and concurrency observations          |
| U04: mail and SLA timing              | No email delivery or timer run observed                  | Authorized test mailbox and controlled timezone/calendar cases               |
| U05: scale and report performance     | No production workload or database access                | Approved workload samples and performance objectives                         |

No Jira API was called, no internal schema was recovered, and no software phase is implicitly authorized. Each phase ends with decisions for the owner before implementation.

## Documentation review

An independent read-only review checked this directory against the research request. The draft incorporates its five functional findings: portal defaults for hidden required fields; explicit material edits, approval invalidation and outcome-specific mandatory gates; retained portal-contract checks during workflow migration; all-member authorization for sprint-wide commands; and SLA goal/calendar relationships with replayable project lifecycle sources. Proposed policies and corresponding acceptance fixtures are in Phases 02, 04 and 06. Description requiredness and conditional criteria validation were also made consistent.

Validation covers the requested file structure, phase requirement/acceptance sections, relative file/heading links, JSON parsing, fenced blocks and formatter checks. Mermaid blocks have structural checks but were not rendered. There is no application code change to exercise with Pest/Vitest/Playwright, and these document checks do not establish implementation readiness or tenant parity.
