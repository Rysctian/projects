I want to use REA (Reverse Engineer Anything) to analyze Jira's ticketing system and help me build my own fully functional Jira-inspired ticketing application.

Target application: Jira Cloud / Jira Service Management

Reference website: https://www.atlassian.com/software/jira/service-management

My goal: Understand Jira's ticketing features, user workflows, UI behavior, data structures, and documented API behavior, then recreate similar functionality in my own application using original code.

## Objective

Study Jira's functionality, processes, business rules, relationships, configurations, and workflows to design a standalone Jira-inspired project and ticket management system.

The goal is to understand how the entire system works and divide its development into **multiple independent phases**, allowing each phase to be reviewed, refined, and approved before implementation.

Focus on **functional requirements, process flows, business rules, system behavior, and database design** rather than UI appearance or coding implementation.

## 1. Reverse Engineering Scope

Investigate Jira's major modules:

1. **Authentication & User Management**
   - Authentication and account lifecycle
   - User creation, activation, and deactivation
   - Dynamic roles and granular permissions
   - Global administration and project-specific access

2. **Projects & Teams**
   - Project creation and management
   - Project configurations and settings
   - Project membership, teams, and assignments
   - Project archiving and access restrictions

3. **Ticket / Issue Management**
   - Issue types, categories, and priorities
   - Ticket creation, editing, assignment, and resolution
   - Estimates, due dates, descriptions, and acceptance criteria
   - Parent-child issues, subtasks, and issue relationships
   - Labels, attachments, and comments

4. **Dynamic Workflow Management**
   - Custom statuses and workflow stages
   - Multiple development, review, QA, and staging stages
   - Configurable transitions between stages
   - Workflow conditions, validators, and permissions
   - Workflow assignment to projects or issue types
   - Workflow modifications and versioning
   - Handling existing tickets when workflows change

5. **Planning & Tracking**
   - Backlog management
   - Kanban and Scrum boards
   - Sprint planning and management
   - Ticket filtering, searching, and sorting
   - Workload monitoring and progress tracking

6. **Collaboration & Notifications**
   - Comments, mentions, and attachments
   - Ticket activity and change history
   - User notifications and assignments
   - Audit trails and accountability

7. **Administration & Reporting**
   - Global settings and project configurations
   - Permission management
   - Workflow configuration
   - Dashboards, reports, and metrics

## 2. Investigation Instructions

- Use REA to inspect accessible Jira functionality, interactions, and processes.
- Investigate each module individually and identify its dependencies.
- Review available official Jira documentation when direct observation is insufficient.
- Distinguish observed behavior, documented behavior, and inferred design.
- Do not assume access to Jira's internal source code or actual database schema.
- Identify validations, restrictions, edge cases, and permission boundaries.
- Focus on understanding **why and how a process works**, not merely listing its features.
- Recommend reasonable simplifications where Jira's functionality is unnecessarily complex for an initial implementation.

## 3. Divide the System into Development Phases

After completing the investigation, propose a logical development roadmap.

Each phase should:

- Have a clear objective and defined scope.
- List functional requirements and expected behavior.
- Document its primary process flows.
- Include relevant business rules and validations.
- Identify required database tables and relationships.
- Identify dependencies on previous phases.
- Define expected deliverables and acceptance criteria.
- Explicitly identify features deferred to later phases.

**Important:** Do not force a predetermined number of phases. Determine the most practical breakdown based on functionality, dependencies, complexity, and maintainability.

Each phase should produce a functional, testable milestone whenever possible.

## 4. Documentation Deliverables

Create the following Markdown documentation structure:

```text
docs/jira-research/
├── OVERVIEW.md
├── FEATURE_INVENTORY.md
├── PROCESS_FLOWS.md
├── DOMAIN_MODEL.md
├── DEVELOPMENT_ROADMAP.md
└── phases/
    ├── PHASE_01.md
    ├── PHASE_02.md
    ├── PHASE_03.md
    └── ...
```

**Documentation requirements:**

- `OVERVIEW.md` — Overall system purpose, modules, and core concepts.
- `FEATURE_INVENTORY.md` — Jira features, responsibilities, behavior, and dependencies.
- `PROCESS_FLOWS.md` — Major end-to-end processes, including Mermaid flowcharts where useful.
- `DOMAIN_MODEL.md` — Core entities, relationships, and recommended MySQL database model. Distinguish proposed schema from Jira's unknown internal implementation.
- `DEVELOPMENT_ROADMAP.md` — Proposed phases, milestones, dependencies, and implementation order.
- `PHASE_XX.md` — Standalone phase requirements, processes, business rules, starting schemas, and deliverables.

Keep documentation **concise but sufficiently detailed** for development agents. Avoid unnecessary explanations, UI design instructions, code snippets, and implementation boilerplate.

## 5. Technology Direction

Use the following technologies when proposing the system architecture:

- Backend: Laravel (latest stable)
- Frontend: Vue 3 (latest stable) + TypeScript
- Integration: Inertia.js
- Database: MySQL
- Styling: Tailwind CSS + shadcn-vue
- Architecture: Modular monolith
- Testing: Pest, Vitest, and Playwright
- Deployment: Docker

Identify additional dependencies only when genuinely required by a phase.

## 6. Restrictions

- **Research and documentation only. Do not implement code.**
- Do not modify existing project specifications or development phases.
- Do not compare the findings with any existing project specification.
- Do not include AI-powered application features, document automation, or autonomous agents.
- Do not copy Jira's proprietary code, branding, or assets.
- Do not invent undiscovered functionality and present it as verified.
- Do not overengineer the architecture.
- Do not combine all phases into one large implementation specification.
- Do not automatically authorize any phase for development.

## 7. Expected Result

Deliver a complete, independently organized functional blueprint of a Jira-inspired ticketing and project management system.

Start with the overall functional investigation, identify the relationships between modules, and then organize the requirements into development phases.

Each phase must be understandable independently while remaining consistent with the overall system design.

**The final output should allow me to review, revise, and solidify each phase individually before authorizing my coding agents to begin implementation.**