# Phase 01 core execution

The user authorized implementation of `docs/phases/astreia-phase-1-foundation.md` on October 20, 2026, prioritizing the core process. Existing stack setup is retained. Work proceeds through identity, project access, configurable workflows, ticket lifecycle and usable screens before secondary reporting.

Engineering decisions within the approved model:

- Forward migrations preserve potentially existing databases; no destructive database command is authorized.
- Portable automated tests use isolated in-memory SQLite. MySQL remains the application database, and MySQL-only validation is reported separately.
- Ticket optimistic `lock_version` is separate from the material-requirements `revision_number`, preventing stale form submissions from overwriting transitions or approvals.
- Additional sign-off visit/version data prevents reuse of approvals after returning to a stage or changing requirements.
- Project permission catalog is seeded from stable keys. Role names are editable templates only.
- Attachment upload rollout is disabled until file limits and accepted types are approved. Private downloads always check project scope and permission.
- Docker remains excluded as requested.

Review-driven safeguards:

- A surviving project access manager must retain both `project.view` and `access.manage`.
- Rework invalidates QA and affected downstream checkpoint approvals, even when a configured closure shortcut exists. Requirement changes and reopening invalidate previous verification cycles.
- Workflow publication requires a reachable verification checkpoint and restricts sign-off gates to review, verification and pre-close semantic stages so tickets cannot become stranded behind an unexecutable gate.
- The board uses independent pagination per configured stage, rather than grouping just one ticket-list page. Summary counts, reports and board data respect ticket-read permission.
