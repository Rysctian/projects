# Secure core for new projects

The owner authorized `docs/jira-research/phases/PHASE_01.md` on 2026-10-21 and chose: use the new core for new projects; preserve existing projects and history.

This decision supersedes the older Phase 01 defaults only for newly created projects. Existing projects retain their categories, workflow versions, mandatory QA and urgent intake behavior. No existing ticket is reclassified or moved. Forward migrations preserve installed databases.

New projects use Open → In progress → Resolved, with a reasoned reopen to Open. Task/Bug/Story, priorities and resolutions are project-owned data. Summary and description are required, criteria optional, and active work requires an eligible assignee. Resolution records success, cancellation, duplicate or another outcome separately from status.

Core projects require explicit membership and project capabilities even for global administrators. Global administration and audited recovery are separate capabilities. Archive blocks domain writes until explicit restoration. Existing unrestricted administration remains only for legacy project compatibility.

Implementation reuses existing table names where their responsibilities already match: modules represent components; project_workflows represent immutable workflow versions; workflow_stages represent statuses; project.current_workflow_id is the current default binding; project_ticket_counters, ticket_activities and audit_logs retain their existing responsibilities. No duplicate stores or history renames are needed. Additional catalogs, invitation records and explicit global grants use forward migrations.

The research roadmap remains unapproved beyond Phase 01. Existing later-phase functionality is preserved for legacy projects, not newly expanded. No Docker or additional dependencies are required.
