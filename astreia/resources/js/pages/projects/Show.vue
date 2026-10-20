<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Settings, Workflow, List, Columns3, Search } from '@lucide/vue';
import PageFrame from '@/components/astreia/PageFrame.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import Pagination from '@/components/astreia/Pagination.vue';
import TicketBadges from '@/components/astreia/TicketBadges.vue';
import TicketFields from '@/components/astreia/TicketFields.vue';
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
const board = ref(props.filters.view === 'board');
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
    router.get(base, { ...filter.data(), view }, { preserveScroll: true });
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
    });
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
            <Button
                v-if="capabilities['tickets.create']"
                @click="creating = !creating"
                ><Plus class="size-4" />New ticket</Button
            >
        </template>
        <div
            v-if="project.status === 'archived'"
            class="rounded-lg border bg-muted px-4 py-3 text-sm"
        >
            This project is archived. Available actions reflect your current
            permissions.
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="(count, key) in counts" :key="key" class="panel !py-4">
                <p class="text-xs text-muted-foreground capitalize">
                    {{ key }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ count }}
                </p>
            </div>
        </div>
        <form
            v-if="creating && capabilities['tickets.create']"
            class="panel space-y-4"
            @submit.prevent="create"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">New ticket</h2>
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_intake_stub"
                        type="checkbox"
                        @change="quickIntake"
                    />Urgent support intake</label
                >
            </div>
            <p
                v-if="form.is_intake_stub"
                class="rounded-md bg-amber-500/10 p-3 text-sm"
            >
                Issue a real ticket key now. Title and description can follow;
                incomplete intake cannot leave its initial stage.
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
            <div class="flex gap-2">
                <Button :disabled="form.processing">{{
                    form.processing
                        ? 'Creating…'
                        : form.is_intake_stub
                          ? 'Issue urgent ticket key'
                          : 'Create ticket'
                }}</Button
                ><Button type="button" variant="ghost" @click="creating = false"
                    >Cancel</Button
                >
            </div>
        </form>
        <section class="space-y-4" aria-label="Tickets">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">
                    Tickets
                    <span
                        v-if="!board"
                        class="ml-1 text-sm font-normal text-muted-foreground"
                        >{{ tickets.data.length }} on this page</span
                    >
                </h2>
                <div class="flex gap-1">
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
            <FormErrors :errors="filter.errors" />
            <form
                class="panel grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
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
                <div class="flex gap-2 sm:col-span-2 xl:col-span-4">
                    <Button size="sm" :disabled="filter.processing"
                        ><Search class="size-4" />Apply filters</Button
                    ><Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="router.get(base, {}, { preserveState: false })"
                        >Clear</Button
                    >
                </div>
            </form>
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
            <div v-else class="space-y-3">
                <p class="muted">
                    All matching tickets, grouped by their assigned workflow
                    stage. Each column has its own pagination; older workflow
                    versions remain visible when they contain matching tickets.
                </p>
                <div class="flex gap-4 overflow-x-auto pb-3">
                    <section
                        v-for="column in boardColumns"
                        :key="column.stage.id"
                        class="w-72 shrink-0 rounded-xl bg-muted/60 p-3"
                    >
                        <h3
                            class="mb-3 flex items-center justify-between text-sm font-medium"
                        >
                            {{ column.stage.name
                            }}<span class="text-xs text-muted-foreground">{{
                                column.tickets.total ??
                                column.tickets.data.length
                            }}</span>
                        </h3>
                        <p class="mb-3 text-xs text-muted-foreground">
                            Workflow v{{ column.workflow_version }}
                        </p>
                        <div class="space-y-2">
                            <Link
                                v-for="ticket in column.tickets.data"
                                :key="ticket.id"
                                :href="`${base}/tickets/${ticket.id}`"
                                class="block space-y-2 rounded-lg border bg-card p-3 hover:border-foreground/30"
                                ><p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ ticket.key }}
                                </p>
                                <p class="text-sm font-medium">
                                    {{
                                        ticket.title || 'Untitled urgent intake'
                                    }}
                                </p>
                                <TicketBadges v-bind="ticket" />
                                <p class="text-xs text-muted-foreground">
                                    {{ ticket.assignee?.name ?? 'Unassigned' }}
                                </p></Link
                            >
                            <p
                                v-if="!column.tickets.data.length"
                                class="py-6 text-center text-xs text-muted-foreground"
                            >
                                No matching tickets
                            </p>
                        </div>
                        <Pagination :links="column.tickets.links" />
                    </section>
                </div>
            </div>
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
