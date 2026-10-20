
# Project Instructions

## Project Overview
This project is an AI-powered enterprise platform for
project management, workflow orchestration, documentation,
development tracking, and quality assurance.

## Engineering Guidelines
- Follow existing project architecture and conventions.
- Prefer simple, maintainable, production-ready solutions.
- Reuse existing components and utilities.
- Avoid duplicated logic and unnecessary dependencies.
- Preserve current functionality unless changes are required.
- Do not introduce unrelated refactoring.
- Validate changes using appropriate tests and checks.

## Specialized Instructions

### Frontend
Before implementing, modifying, or reviewing frontend code,
read and follow `FRONTEND.md`.

Applies to:
- Vue components and composables
- Inertia pages and navigation
- Frontend state management
- UI/UX and styling
- Forms and client-side validation
- Frontend testing and performance

### Backend
Before implementing, modifying, or reviewing backend code,
read and follow `BACKEND.md`.

Applies to:
- Controllers, services, and business logic
- Database models, migrations, and queries
- Authentication and authorization
- Validation and security
- Jobs, events, and background processing
- Backend testing and performance

### Full-Stack Tasks
If a task involves frontend and backend changes:
1. Read both `FRONTEND.md` and `BACKEND.md`.
2. Identify the affected interfaces and dependencies.
3. Maintain consistent contracts between both layers.
4. Validate the complete feature end-to-end.

## Instruction Priority
- Follow these project-wide instructions.
- Follow applicable specialized documentation.
- If guidance conflicts, report the conflict and
  follow the more specific applicable guidance
  unless higher-priority instructions override it.

## Completion Requirements
- Review changes for correctness and security.
- Run relevant linting, type checks, and tests.
- Report tests that could not be run.
- Summarize changes and outstanding risks.


