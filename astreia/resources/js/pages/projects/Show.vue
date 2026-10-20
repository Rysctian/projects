<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Plus,
    Settings,
    Workflow,
    List,
    Columns3,
    Search,
    SlidersHorizontal,
    CircleDot,
    Ban,
    Clock3,
    CircleCheck,
    X,
} from '@lucide/vue';
import PageFrame from '@/components/astreia/PageFrame.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import Pagination from '@/components/astreia/Pagination.vue';
import TicketBadges from '@/components/astreia/TicketBadges.vue';
import TicketFields from '@/components/astreia/TicketFields.vue';
import FormSurface from '@/components/astreia/FormSurface.vue';
import TicketKanban from './TicketKanban.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import type {
    Capabilities,
    Label,
    Module,
    Paginated,
    Person,
    Project,
    Stage,
    TicketSummary,
} from '@/types/astreia';
type Filters = {
    q?: string;
    category?: string;
    priority?: string;
    stage_id?: string | number;
    assigned_to?: string | number;
    module_id?: string | number;
    due?: string;
    view?: 'list' | 'board';
};
const props = defineProps<{
    project: Project;
    tickets: Paginated<TicketSummary>;
    filters: Filters;
    stages: Stage[];
    eligibleAssignees: Person[];
    modules: Module[];
    categories: string[];
    priorities: string[];
    capabilities: Capabilities;
    counts: { open: number; blocked: number; overdue: number; closed: number };
    labels: Label[];
    boardColumns: {
        stage: Stage;
        workflow_version: number;
        tickets: Paginated<TicketSummary>;
    }[];
    reports: {
        by_stage: { id: number; name: string; total: number }[];
        by_category: { name: string; total: number }[];
        workload: { assigned_to: number | null; name: string; total: number }[];
    };
}>();
const base = `/projects/${props.project.id}`;
const creating = ref(false);
const board = computed(() => props.filters.view === 'board');
const filtering = ref(false);
const activeFilters = computed(
    () =>
        Object.entries(props.filters).filter(
            ([key, value]) => key !== 'view' && value !== '' && value != null,
        ).length,
);
const metricIcons = {
    open: CircleDot,
    blocked: Ban,
    overdue: Clock3,
    closed: CircleCheck,
};
const form = useForm({
    title: '',
    description: '',
    category: props.categories[0] ?? 'new_feature',
    priority: 'normal',
    module_id: null as number | null,
    assigned_to: null as number | null,
    estimate_minutes: null as number | null,
    due_date: '',
    acceptance_criteria: [] as string[],
    is_intake_stub: false,
});
const filter = useForm({
    q: props.filters.q ?? '',
    category: props.filters.category ?? '',
    priority: props.filters.priority ?? '',
    stage_id: props.filters.stage_id ?? '',
    assigned_to: props.filters.assigned_to ?? '',
    module_id: props.filters.module_id ?? '',
    due: props.filters.due ?? '',
    view: props.filters.view ?? 'list',
});
function setView(view: 'list' | 'board') {
    filter.view = view;
    router.get(
        base,
        { ...props.filters, view },
        { preserveScroll: true, preserveState: true },
    );
}
function quickIntake() {
    if (form.is_intake_stub) {
        form.category = 'support';
        form.priority = 'urgent';
    }
}
function create() {
    form.transform((data) => ({
        ...data,
        estimate_minutes:
            data.estimate_minutes === null ||
            String(data.estimate_minutes) === ''
                ? null
                : Number(data.estimate_minutes),
        acceptance_criteria: data.acceptance_criteria
            .map((value) => value.trim())
            .filter(Boolean),
    })).post(`${base}/tickets`, {
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}
function search() {
    filter.get(base, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            filtering.value = false;
        },
    });
}
function clearFilters() {
    filter.reset();
    filter.q = '';
    filter.category = '';
    filter.priority = '';
    filter.stage_id = '';
    filter.assigned_to = '';
    filter.module_id = '';
    filter.due = '';
    filter.view = board.value ? 'board' : 'list';
    search();
}
</script>
<template>
    <PageFrame
        :title="project.name"
        :eyebrow="project.code"
        :description="project.description ?? undefined"
        back="/projects"
        back-label="All projects"
    >
        <template #actions>
            <Button
                v-if="capabilities['workflows.manage']"
                variant="outline"
                as-child
                ><Link :href="`${base}/workflows`"
                    ><Workflow class="size-4" />Workflow</Link
                ></Button
            >
            <Button
                v-if="
                    capabilities['project.manage'] ||
                    capabilities['members.manage'] ||
                    capabilities['access.manage'] ||
                    capabilities['modules.manage'] ||
                    capabilities['labels.manage']
                "
                variant="outline"
                as-child
                ><Link :href="`${base}/settings`"
                    ><Settings class="size-4" />Settings</Link
                ></Button
            >
            <FormSurface
                v-if="capabilities['tickets.create']"
                v-model:open="creating"
                title="New ticket"
                description="Give the work a little context. Your team's next step starts here."
                mode="drawer"
                :busy="form.processing"
            >
                <template #trigger
                    ><Button
                        ><Plus class="size-4" />New ticket</Button
                    ></template
                >
                <form class="space-y-5" @submit.prevent="create">
                    <label
                        class="flex items-center justify-between gap-3 rounded-lg border bg-muted/40 p-3 text-xs"
                    >
                        <span
                            ><span class="block font-medium"
                                >Urgent support intake</span
                            ><span class="mt-1 block text-muted-foreground"
                                >Issue a ticket key now, add the details
                                later.</span
                            ></span
                        >
                        <input
                            v-model="form.is_intake_stub"
                            type="checkbox"
                            @change="quickIntake"
                        />
                    </label>
                    <p
                        v-if="form.is_intake_stub"
                        class="rounded-md bg-amber-500/10 p-3 text-xs"
                    >
                        Incomplete intake cannot leave its initial stage until
                        the required details are added.
                    </p>
                    <FormErrors :errors="form.errors" />
                    <TicketFields
                        :form="form"
                        :categories="categories"
                        :priorities="priorities"
                        :modules="modules"
                        :assignees="eligibleAssignees"
                        editable
                        :assignable="!!capabilities['tickets.assign']"
                        :incomplete="form.is_intake_stub"
                    />
                    <div
                        class="flex items-center justify-end gap-2 border-t pt-4"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            @click="creating = false"
                            >Cancel</Button
                        >
                        <Button :disabled="form.processing"
                            ><Plus class="size-4" />{{
                                form.processing
                                    ? 'Creating…'
                                    : form.is_intake_stub
                                      ? 'Issue urgent ticket key'
                                      : 'Create ticket'
                            }}</Button
                        >
                    </div>
                </form>
            </FormSurface>
        </template>
        <div
            v-if="project.status === 'archived'"
            class="rounded-lg border bg-muted px-4 py-3 text-sm"
        >
            This project is archived. Available actions reflect your current
            permissions.
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                v-for="(count, key) in counts"
                :key="key"
                class="panel flex items-center justify-between gap-3 !py-4"
            >
                <div>
                    <p
                        class="text-[11px] font-medium text-muted-foreground capitalize"
                    >
                        {{ key }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ count }}
                    </p>
                </div>
                <span
                    :class="[
                        'flex size-9 items-center justify-center rounded-lg',
                        key === 'closed'
                            ? 'bg-emerald-500/8 text-emerald-600 dark:text-emerald-400'
                            : key === 'blocked' || key === 'overdue'
                              ? 'bg-amber-500/8 text-amber-600 dark:text-amber-400'
                              : 'bg-primary/8 text-primary',
                    ]"
                    ><component
                        :is="metricIcons[key]"
                        class="size-4"
                        :stroke-width="1.7"
                /></span>
            </div>
        </div>
        <section class="space-y-4" aria-label="Tickets">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b pb-4"
            >
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <Columns3 class="size-4 text-muted-foreground" />
                    Tickets
                    <span
                        v-if="!board"
                        class="ml-1 text-sm font-normal text-muted-foreground"
                        >{{ tickets.data.length }} on this page</span
                    >
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="filtering = true"
                        ><SlidersHorizontal class="size-3.5" />Filters<Badge
                            v-if="activeFilters"
                            variant="secondary"
                            class="px-1.5 text-[10px]"
                            >{{ activeFilters }}</Badge
                        ></Button
                    >
                    <Button
                        v-if="activeFilters"
                        size="sm"
                        variant="ghost"
                        @click="clearFilters"
                        ><X class="size-3.5" />Clear filters</Button
                    >
                    <div class="flex gap-1 rounded-lg border bg-muted/50 p-0.5">
                        <Button
                            size="sm"
                            :variant="!board ? 'secondary' : 'ghost'"
                            :aria-pressed="!board"
                            @click="setView('list')"
                            ><List class="size-4" />List</Button
                        ><Button
                            size="sm"
                            :variant="board ? 'secondary' : 'ghost'"
                            :aria-pressed="board"
                            @click="setView('board')"
                            ><Columns3 class="size-4" />Board</Button
                        >
                    </div>
                </div>
            </div>
            <FormSurface
                v-model:open="filtering"
                title="Filter tickets"
                description="Find the right work by stage, assignee, priority, or a few keywords."
                :busy="filter.processing"
            >
                <FormErrors :errors="filter.errors" />
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="search"
                >
                    <Input
                        v-model="filter.q"
                        aria-label="Search tickets"
                        placeholder="Search key, title, description…"
                        class="sm:col-span-2"
                    />
                    <select
                        v-model="filter.stage_id"
                        aria-label="Filter by stage"
                        class="control"
                    >
                        <option value="">All stages</option>
                        <option
                            v-for="stage in stages"
                            :key="stage.id"
                            :value="stage.id"
                        >
                            {{ stage.name }}
                        </option>
                    </select>
                    <select
                        v-model="filter.assigned_to"
                        aria-label="Filter by assignee"
                        class="control"
                    >
                        <option value="">All assignees</option>
                        <option
                            v-for="user in eligibleAssignees"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }}
                        </option>
                    </select>
                    <select
                        v-model="filter.category"
                        aria-label="Filter by category"
                        class="control"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="value in categories"
                            :key="value"
                            :value="value"
                        >
                            {{ value.replaceAll('_', ' ') }}
                        </option>
                    </select>
                    <select
                        v-model="filter.priority"
                        aria-label="Filter by priority"
                        class="control"
                    >
                        <option value="">All priorities</option>
                        <option
                            v-for="value in priorities"
                            :key="value"
                            :value="value"
                        >
                            {{ value }}
                        </option>
                    </select>
                    <select
                        v-model="filter.module_id"
                        aria-label="Filter by module"
                        class="control"
                    >
                        <option value="">All modules</option>
                        <option
                            v-for="module in modules"
                            :key="module.id"
                            :value="module.id"
                        >
                            {{ module.name }}
                        </option>
                    </select>
                    <select
                        v-model="filter.due"
                        aria-label="Filter by due date"
                        class="control"
                    >
                        <option value="">Any due date</option>
                        <option value="overdue">Overdue</option>
                        <option value="today">Due today</option>
                    </select>
                    <div
                        class="flex justify-end gap-2 border-t pt-4 sm:col-span-2"
                    >
                        <Button size="sm" :disabled="filter.processing"
                            ><Search class="size-4" />Apply filters</Button
                        ><Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="clearFilters"
                            >Clear</Button
                        >
                    </div>
                </form>
            </FormSurface>
            <div v-if="!board && !tickets.data.length" class="panel empty">
                No tickets match these filters.
            </div>
            <div v-else-if="!board" class="overflow-x-auto rounded-xl border">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Stage</th>
                            <th>Priority / flags</th>
                            <th>Assignee</th>
                            <th>Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="hover:bg-muted/40"
                        >
                            <td class="min-w-64">
                                <Link
                                    :href="`${base}/tickets/${ticket.id}`"
                                    class="group"
                                    ><span
                                        class="font-mono text-xs text-muted-foreground"
                                        >{{ ticket.key }}</span
                                    >
                                    <p
                                        class="mt-1 font-medium group-hover:underline"
                                    >
                                        {{
                                            ticket.title ||
                                            'Untitled urgent intake'
                                        }}
                                    </p></Link
                                >
                                <p
                                    class="mt-1 text-xs text-muted-foreground capitalize"
                                >
                                    {{ ticket.category.replaceAll('_', ' ') }}
                                </p>
                            </td>
                            <td>
                                <Badge variant="outline">{{
                                    ticket.stage.name
                                }}</Badge>
                            </td>
                            <td><TicketBadges v-bind="ticket" /></td>
                            <td class="whitespace-nowrap">
                                {{ ticket.assignee?.name ?? 'Unassigned' }}
                            </td>
                            <td class="whitespace-nowrap text-muted-foreground">
                                {{ ticket.due_date?.slice(0, 10) ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <TicketKanban v-else :base="base" :columns="boardColumns" />
            <Pagination v-if="!board" :links="tickets.links" />
        </section>
        <details v-if="capabilities['tickets.view']" class="panel">
            <summary>Project reporting</summary>
            <p class="muted mt-3">
                Totals cover the full project. Workload counts open,
                non-cancelled tickets.
            </p>
            <div class="mt-4 grid gap-5 lg:grid-cols-3">
                <section>
                    <h3 class="mb-3 font-medium">By stage</h3>
                    <dl class="space-y-2">
                        <div
                            v-for="row in reports.by_stage"
                            :key="row.id"
                            class="flex justify-between gap-4 text-sm"
                        >
                            <dt>{{ row.name }}</dt>
                            <dd class="tabular-nums">{{ row.total }}</dd>
                        </div>
                    </dl>
                </section>
                <section>
                    <h3 class="mb-3 font-medium">By category</h3>
                    <dl class="space-y-2">
                        <div
                            v-for="row in reports.by_category"
                            :key="row.name"
                            class="flex justify-between gap-4 text-sm"
                        >
                            <dt class="capitalize">
                                {{ row.name.replaceAll('_', ' ') }}
                            </dt>
                            <dd class="tabular-nums">{{ row.total }}</dd>
                        </div>
                    </dl>
                </section>
                <section>
                    <h3 class="mb-3 font-medium">Open workload</h3>
                    <dl class="space-y-2">
                        <div
                            v-for="row in reports.workload"
                            :key="row.assigned_to ?? 'unassigned'"
                            class="flex justify-between gap-4 text-sm"
                        >
                            <dt>{{ row.name }}</dt>
                            <dd class="tabular-nums">{{ row.total }}</dd>
                        </div>
                    </dl>
                    <p v-if="!reports.workload.length" class="muted">
                        No open tickets.
                    </p>
                </section>
            </div>
        </details>
    </PageFrame>
</template>
