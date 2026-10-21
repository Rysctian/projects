import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, nextTick, reactive } from 'vue';
import type { App, Component } from 'vue';
import { router } from '@inertiajs/vue3';
import TicketFields from '@/components/astreia/TicketFields.vue';
import TicketActions from './TicketActions.vue';
import TicketShow from './Show.vue';
import ProjectShow from '../projects/Show.vue';
import ProjectSettings from '../projects/Settings.vue';
import ProjectIndex from '../projects/Index.vue';

// Render form contents inline so these tests exercise page behavior without portals.
vi.mock('@/components/astreia/FormSurface.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', [slots.trigger?.(), slots.default?.()]),
    }),
}));
vi.mock('@/components/astreia/PageFrame.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('main', [slots.actions?.(), slots.default?.()]),
    }),
}));

let app: App | undefined;
let root: HTMLDivElement;
function mount(component: Component, props: Record<string, unknown>) {
    root = document.createElement('div');
    document.body.append(root);
    app = createApp(component, props);
    app.mount(root);
    return root;
}
afterEach(() => {
    app?.unmount();
    root?.remove();
    vi.restoreAllMocks();
});
const workTypes = [
    { id: 12, code: 'task', name: 'Task', is_active: true },
    { id: 13, code: 'retired', name: 'Retired type', is_active: false },
];
const priorityOptions = [
    { id: 21, code: 'normal', name: 'Normal', is_active: true, rank: 2 },
];
const resolutions = [
    {
        id: 31,
        code: 'duplicate',
        name: 'Duplicate',
        is_active: true,
        outcome: 'duplicate',
    },
    {
        id: 32,
        code: 'old',
        name: 'Old outcome',
        is_active: false,
        outcome: 'cancelled',
    },
];
const project = {
    id: 1,
    code: 'CORE',
    name: 'Core',
    description: '',
    status: 'active',
    process_model: 'core',
    timezone: 'UTC',
    lock_version: 4,
};
const ticket = {
    id: 2,
    key: 'CORE-00002',
    title: 'A task',
    description: 'Context',
    category: 'task',
    priority: 'normal',
    work_type_id: 12,
    priority_id: 21,
    resolution_id: null,
    resolution: null,
    closed_at: null,
    stage: { id: 2, name: 'In progress', type: 'active' },
    assigned_to: 7,
    assignee: { id: 7, name: 'Former worker' },
    due_date: null,
    is_intake_stub: false,
    is_blocked: false,
    is_cancelled: false,
    lock_version: 3,
    workflow: { id: 1, version_number: 1 },
    revision_number: 1,
    stage_visit: 1,
    module_id: null,
    estimate_minutes: 0,
    acceptance_criteria: [],
    completion_notes: null,
    documentation_impact: null,
    documentation_impact_note: null,
    reporter: { id: 1, name: 'Reporter' },
    module: null,
    labels: [],
    needs_reassignment: true,
};
const capabilities = Object.fromEntries(
    [
        'tickets.create',
        'tickets.edit',
        'tickets.assign',
        'tickets.transition',
        'tickets.close',
        'tickets.verify',
        'tickets.comment',
        'tickets.attach',
        'project.manage',
        'project.restore',
        'workflows.manage',
        'tickets.view',
        'labels.manage',
        'modules.manage',
        'members.manage',
        'access.manage',
    ].map((key) => [key, true]),
);
const catalogs = {
    workTypes,
    priorityOptions,
    resolutions,
    categories: ['new_feature'],
    priorities: ['normal'],
    modules: [],
    eligibleAssignees: [],
    labels: [],
};
const listProps = {
    project,
    ...catalogs,
    capabilities,
    tickets: { data: [ticket], links: [] },
    filters: {},
    stages: [],
    counts: { open: 2, closed: 1, overdue: 1 },
    boardColumns: [],
    reports: { by_stage: [], by_category: [], workload: [] },
};
async function select(label: string, value: string) {
    const input = root.querySelector<HTMLSelectElement>(
        `select[aria-label="${label}"]`,
    )!;
    expect(input, label).not.toBeNull();
    input.value = value;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    await nextTick();
}
function submit(form: HTMLFormElement) {
    form.dispatchEvent(
        new Event('submit', { bubbles: true, cancelable: true }),
    );
}
async function enter(selector: string, value: string) {
    const input = root.querySelector<HTMLInputElement | HTMLTextAreaElement>(
        selector,
    )!;
    expect(input, selector).not.toBeNull();
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await nextTick();
}
const detailProps = {
    project,
    ticket,
    ...catalogs,
    capabilities,
    transitions: [],
    activities: [],
    comments: [],
    signoffs: [],
    attachments: [],
    uploadsEnabled: true,
};
const settingsProps = {
    project,
    ...catalogs,
    members: [],
    roles: [],
    users: [],
    permissionKeys: [],
    grantablePermissions: [],
    capabilities,
};

describe('secure core frontend', () => {
    it('selects active catalog IDs and keeps criteria optional in core fields', async () => {
        const form = reactive({
            ...ticket,
            title: 'A task',
            description: 'Context',
            due_date: '',
            acceptance_criteria: [],
        });
        mount(TicketFields, {
            form,
            ...catalogs,
            core: true,
            assignees: [],
            editable: true,
            assignable: true,
        });
        expect(root.textContent).toContain('Work type');
        expect(root.textContent).not.toContain('Retired type');
        expect(root.textContent).not.toContain('Required for feature');
        await select('Work type', '12');
        expect(form.work_type_id).toBe(12);
        expect(root.querySelector('select[aria-label="Category"]')).toBeNull();
    });

    it('creates core tickets with catalog IDs, no legacy intake fields, and a zero estimate', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        mount(ProjectShow, listProps);
        const estimate = root.querySelector<HTMLInputElement>(
            'input[type="number"]',
        )!;
        estimate.value = '0';
        estimate.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
        submit(root.querySelector('form')!);
        expect(post).toHaveBeenCalledWith(
            '/projects/1/tickets',
            expect.objectContaining({
                work_type_id: 12,
                priority_id: 21,
                estimate_minutes: 0,
            }),
            expect.anything(),
        );
        const data = post.mock.calls[0][1];
        expect(data).not.toHaveProperty('category');
        expect(data).not.toHaveProperty('priority');
        expect(data).not.toHaveProperty('is_intake_stub');
        expect(root.textContent).not.toContain('Urgent support intake');
        expect(root.textContent).not.toContain('Board');
        expect(root.textContent).not.toContain('Project reporting');
    });

    it('sends resolution IDs and notes without documentation fields or QA for core', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        mount(TicketActions, {
            base: '/projects/1/tickets/2',
            ticket,
            core: true,
            resolutions,
            capabilities,
            transitions: [
                {
                    id: 5,
                    to_stage: { id: 3, name: 'Resolved', type: 'closed' },
                    requires_reason: false,
                    requires_signoff: false,
                },
            ],
        });
        const destination = root.querySelector('select')!;
        destination.value = '5';
        destination.dispatchEvent(new Event('change', { bubbles: true }));
        await nextTick();
        await select('Resolution', '31');
        const notes =
            root.querySelector<HTMLTextAreaElement>('textarea[required]')!;
        notes.value = 'Same issue as CORE-00001';
        notes.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
        submit(root.querySelector('form')!);
        expect(post).toHaveBeenCalledWith(
            '/projects/1/tickets/2/transitions',
            expect.objectContaining({
                resolution_id: 31,
                completion_notes: 'Same issue as CORE-00001',
                lock_version: 3,
            }),
            expect.anything(),
        );
        expect(post.mock.calls[0][1]).not.toHaveProperty(
            'documentation_impact',
        );
        expect(root.textContent).not.toContain('Block ticket');
        expect(root.textContent).not.toContain('Cancel ticket');
        expect(root.textContent).not.toContain('Old outcome');
    });

    it('preserves legacy fields and block/cancel actions', () => {
        mount(TicketActions, {
            base: '/tickets/2',
            ticket,
            capabilities,
            transitions: [],
        });
        expect(root.textContent).toContain('Block ticket');
        expect(root.textContent).toContain('Cancel ticket');
    });

    it('shows reassignment and activity while hiding deferred core collaboration', () => {
        mount(TicketShow, {
            project,
            ticket,
            ...catalogs,
            capabilities,
            transitions: [],
            activities: [],
            comments: [],
            signoffs: [],
            attachments: [],
            uploadsEnabled: true,
        });
        expect(root.textContent).toMatch(/reassign/i);
        expect(root.textContent).toContain('Activity');
        expect(root.textContent).not.toContain('Attachments');
        expect(root.textContent).not.toContain('Post comment');
        expect(root.textContent).not.toContain('Verification');
    });

    it('allows restore without project.manage and prevents other archived settings writes', () => {
        mount(ProjectSettings, {
            project: { ...project, status: 'archived' },
            ...catalogs,
            members: [],
            roles: [],
            users: [],
            permissionKeys: [],
            grantablePermissions: [],
            capabilities: { ...capabilities, 'project.manage': false },
        });
        expect(root.textContent).toContain('Restore project');
        expect(root.textContent).not.toContain('Add member');
        expect(root.textContent).not.toContain('Add module');
        expect(root.textContent).not.toContain('Create role');
        expect(root.querySelector('a[href$="/workflows"]')).toBeNull();
    });

    it('defaults new projects to UTC and filters the project index by archive status', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mount(ProjectIndex, {
            projects: { data: [], links: [] },
            canCreate: true,
            filters: { status: 'active' },
        });
        submit(root.querySelector('form')!);
        expect(post).toHaveBeenCalledWith(
            '/projects',
            expect.objectContaining({ timezone: 'UTC' }),
            expect.anything(),
        );
        await select('Project status', 'archived');
        expect(get).toHaveBeenCalledWith(
            '/projects',
            { status: 'archived' },
            expect.anything(),
        );
    });

    it('recovers only a listed project through the audited reason endpoint', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        mount(ProjectIndex, {
            projects: { data: [], links: [] },
            canCreate: false,
            filters: { status: 'active' },
            recoverableProjects: [{ id: 9, code: 'OPS', name: 'Operations' }],
        });
        expect(root.querySelector('a[href="/projects/9"]')).toBeNull();
        await select('Project to recover', '9');
        await enter(
            'textarea[aria-label="Recovery reason"]',
            'Restore the missing access manager',
        );
        submit(root.querySelector('form')!);
        expect(post).toHaveBeenCalledWith(
            '/projects/9/recover-access',
            { reason: 'Restore the missing access manager' },
            expect.anything(),
        );
    });

    it('patches core details with timezone, archive reason and the loaded version', async () => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
        mount(ProjectSettings, settingsProps);
        await select('Project status', 'archived');
        await enter('input[aria-label="Project timezone"]', 'Asia/Singapore');
        await enter(
            'textarea[aria-label="Status change reason"]',
            'Project completed',
        );
        submit(root.querySelector('form')!);
        expect(patch).toHaveBeenCalledWith(
            '/projects/1',
            {
                name: 'Core',
                description: '',
                timezone: 'Asia/Singapore',
                status: 'archived',
                lock_version: 4,
                reason: 'Project completed',
            },
            expect.anything(),
        );
    });

    it('restores with its own capability and requires a reason', async () => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
        mount(ProjectSettings, {
            ...settingsProps,
            project: { ...project, status: 'archived' },
            capabilities: { 'project.restore': true },
        });
        const reason = root.querySelector<HTMLTextAreaElement>(
            'textarea[aria-label="Restoration reason"]',
        )!;
        expect(reason?.required).toBe(true);
        await enter(
            'textarea[aria-label="Restoration reason"]',
            'Resume delivery',
        );
        submit(root.querySelector('form')!);
        expect(patch).toHaveBeenCalledWith(
            '/projects/1',
            {
                name: 'Core',
                description: '',
                timezone: 'UTC',
                status: 'active',
                lock_version: 4,
                reason: 'Resume delivery',
            },
            expect.anything(),
        );
    });

    it('edits core tickets with catalog IDs and excludes protected and deferred fields', () => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
        mount(TicketShow, detailProps);
        submit(root.querySelector('form')!);
        expect(patch).toHaveBeenCalledWith(
            '/projects/1/tickets/2',
            {
                lock_version: 3,
                title: 'A task',
                description: 'Context',
                work_type_id: 12,
                priority_id: 21,
                assigned_to: 7,
                module_id: null,
                estimate_minutes: 0,
                due_date: '',
                acceptance_criteria: [],
            },
            expect.anything(),
        );
    });

    it('keeps assignment-only edits limited to the assignment and version', () => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
        mount(TicketShow, {
            ...detailProps,
            capabilities: { 'tickets.assign': true },
        });
        submit(root.querySelector('form')!);
        expect(patch).toHaveBeenCalledWith(
            '/projects/1/tickets/2',
            { lock_version: 3, assigned_to: 7 },
            expect.anything(),
        );
    });

    it('reopens resolved core tickets with a reason through actions', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        mount(TicketActions, {
            base: '/projects/1/tickets/2',
            ticket: {
                ...ticket,
                stage: { id: 3, name: 'Resolved', type: 'closed' },
            },
            core: true,
            resolutions,
            capabilities,
            transitions: [],
        });
        await select('Ticket action', 'reopen');
        await enter('textarea[required]', 'The issue returned');
        submit(root.querySelector('form')!);
        expect(post).toHaveBeenCalledWith(
            '/projects/1/tickets/2/actions',
            { action: 'reopen', reason: 'The issue returned', lock_version: 3 },
            expect.anything(),
        );
    });

    it('shows resolution outcome and historical outcomes after reopen', () => {
        mount(TicketShow, {
            ...detailProps,
            ticket: {
                ...ticket,
                resolution: resolutions[0],
                resolution_id: 31,
                closed_at: '2026-10-21T12:00:00Z',
                completion_notes: 'Already tracked',
                stage: { id: 3, name: 'Resolved', type: 'closed' },
            },
            activities: [
                {
                    id: 1,
                    activity_type: 'transitioned',
                    payload: {
                        resolution_id: 31,
                        resolution: { name: 'Duplicate', outcome: 'duplicate' },
                        completion_notes: 'Already tracked',
                    },
                    actor: null,
                    created_at: '2026-10-21T12:00:00Z',
                },
            ],
        });
        expect(root.textContent).toContain('Resolution: Duplicate');
        expect(root.textContent).toContain('Outcome: duplicate');
        expect(
            root.querySelector('time[datetime="2026-10-21T12:00:00Z"]'),
        ).not.toBeNull();
    });

    it('sends core filters and sorting without legacy fields and clears them', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mount(ProjectShow, {
            ...listProps,
            filters: {
                q: 'CORE-00002',
                state: 'open',
                work_type_id: 12,
                priority_id: 21,
            },
        });
        await select('Sort tickets', 'priority');
        await select('Sort direction', 'asc');
        submit(root.querySelectorAll('form')[1]);
        expect(get).toHaveBeenCalledWith(
            '/projects/1',
            expect.objectContaining({
                q: 'CORE-00002',
                state: 'open',
                work_type_id: 12,
                priority_id: 21,
                sort: 'priority',
                direction: 'asc',
            }),
            expect.anything(),
        );
        expect(get.mock.calls[0][1]).not.toHaveProperty('category');
        expect(get.mock.calls[0][1]).not.toHaveProperty('view');
        const clear = Array.from(root.querySelectorAll('button')).find(
            (button) => button.textContent?.includes('Clear filters'),
        )!;
        clear.click();
        await nextTick();
        expect(get).toHaveBeenLastCalledWith(
            '/projects/1',
            expect.objectContaining({
                q: '',
                state: '',
                work_type_id: '',
                priority_id: '',
                sort: 'number',
                direction: 'desc',
            }),
            expect.anything(),
        );
    });

    it('hides every core ticket write on archived projects even when capabilities remain true', () => {
        mount(TicketShow, {
            ...detailProps,
            project: { ...project, status: 'archived' },
            transitions: [
                {
                    id: 5,
                    to_stage: { id: 3, name: 'Resolved', type: 'closed' },
                    requires_reason: false,
                    requires_signoff: false,
                },
            ],
        });
        expect(root.querySelector('form')).toBeNull();
        expect(root.textContent).toContain('archived');
        expect(root.textContent).toContain('Activity');
    });

    it('preserves legacy creation fields and deferred views', () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        mount(ProjectShow, {
            ...listProps,
            project: { ...project, process_model: 'legacy' },
        });
        submit(root.querySelector('form')!);
        expect(post.mock.calls[0][1]).toMatchObject({
            category: 'new_feature',
            priority: 'normal',
            is_intake_stub: false,
        });
        expect(post.mock.calls[0][1]).not.toHaveProperty('work_type_id');
        expect(root.textContent).toContain('Board');
        expect(root.textContent).toContain('Urgent support intake');
    });
});
