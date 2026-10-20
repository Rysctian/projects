
# Frontend Architecture

## Folder Structure
- Organize Inertia pages by business feature.
- Keep feature-specific components inside their feature.
- Keep feature-specific composables near their pages.
- Place only genuinely reusable components in shared/.
- Place UI primitives in components/ui/.
- Use layouts/ for application-level layouts.
- Use lib/ for pure utility functions.
- Co-locate feature-specific types where practical.

## Component Guidelines
- Use Vue 3 Composition API with script setup.
- Keep page components focused on page composition.
- Extract complex UI into smaller components.
- Use composables for reusable reactive behavior.
- Prefer computed over watchers for derived state.
- Avoid unnecessary global state.
- Avoid adding abstraction layers prematurely.

## Inertia Guidelines
- Use Inertia for page navigation and server forms.
- Match page paths with the configured resolver.
- Keep authorization and validation server-side.
- Preserve existing Inertia conventions.
- Don't introduce Vue Router unnecessarily.

## Maintainability
- Prefer readability over clever abstractions.
- Avoid deeply nested folders.
- Avoid generic filenames like helpers.ts everywhere.
- Don't create folders for hypothetical future features.
- Keep related code close together.
- Extract shared functionality only when needed.
- Follow existing naming conventions consistently.
