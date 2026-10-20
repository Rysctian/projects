<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CalendarDays,
    Circle,
    CircleCheck,
    CircleDashed,
    Layers,
    UserRound,
} from '@lucide/vue';
import Pagination from '@/components/astreia/Pagination.vue';
import TicketBadges from '@/components/astreia/TicketBadges.vue';
import type { Paginated, Stage, TicketSummary } from '@/types/astreia';

defineProps<{
    base: string;
    columns: {
        stage: Stage;
        workflow_version: number;
        tickets: Paginated<TicketSummary>;
    }[];
}>();
function initials(name: string) {
    return name
        .split(' ')
        .map((word) => word[0])
        .slice(0, 2)
        .join('');
}
function stageColor(type: string) {
    return (
        {
            intake: 'text-slate-400',
            ready: 'text-sky-500',
            work: 'text-indigo-500',
            review: 'text-violet-500',
            verification: 'text-amber-500',
            pre_close: 'text-teal-500',
            closed: 'text-emerald-500',
        }[type] ?? 'text-slate-400'
    );
}
function dueDate(date: string) {
    return new Date(`${date.slice(0, 10)}T12:00:00`).toLocaleDateString(
        undefined,
        { month: 'short', day: 'numeric' },
    );
}
</script>

<template>
    <div
        role="region"
        aria-label="Kanban board"
        tabindex="0"
        class="board-scroll flex items-start gap-4 overflow-x-auto rounded-xl pb-5 focus-visible:outline-2 focus-visible:outline-ring"
    >
        <section
            v-for="column in columns"
            :key="column.stage.id"
            :aria-label="`${column.stage.name}, workflow version ${column.workflow_version}`"
            class="kanban-column min-h-[300px] w-[290px] shrink-0 rounded-xl border border-border/50 p-2.5 sm:w-[306px]"
        >
            <header
                class="flex items-center justify-between gap-2 px-1.5 pt-1 pb-3"
            >
                <h3 class="flex items-center gap-2 text-xs font-semibold">
                    <component
                        :is="
                            column.stage.type === 'closed'
                                ? CircleCheck
                                : column.stage.type === 'intake'
                                  ? CircleDashed
                                  : Circle
                        "
                        :class="['size-3.5', stageColor(column.stage.type)]"
                        :stroke-width="2.5"
                    />
                    {{ column.stage.name }}
                    <span
                        class="rounded-md bg-background px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground tabular-nums"
                        >{{
                            column.tickets.total ?? column.tickets.data.length
                        }}</span
                    >
                </h3>
                <span
                    class="text-[10px] text-muted-foreground/70"
                    :title="`Workflow version ${column.workflow_version}`"
                    >v{{ column.workflow_version }}</span
                >
            </header>
            <TransitionGroup
                name="kanban"
                tag="div"
                appear
                class="relative space-y-2.5"
            >
                <Link
                    v-for="(ticket, index) in column.tickets.data"
                    :key="ticket.id"
                    :href="`${base}/tickets/${ticket.id}`"
                    :style="{ '--card-delay': `${Math.min(index, 4) * 35}ms` }"
                    class="kanban-card group block rounded-lg border border-border/80 bg-card p-4 hover:border-primary/30"
                >
                    <div class="mb-2.5 flex items-center justify-between gap-2">
                        <span
                            class="text-[10px] font-medium tracking-wide text-muted-foreground"
                            >{{ ticket.key }}</span
                        >
                        <ArrowUpRight
                            class="size-3.5 text-muted-foreground/50 group-hover:text-primary"
                        />
                    </div>
                    <p
                        class="mb-3 text-[13px] leading-[1.55] font-medium tracking-[-0.01em]"
                    >
                        {{ ticket.title || 'Untitled urgent intake' }}
                    </p>
                    <TicketBadges v-bind="ticket" />
                    <div
                        class="mt-4 flex items-center gap-1.5 text-[10px] text-muted-foreground"
                    >
                        <Layers class="size-3" /><span class="capitalize">{{
                            ticket.category.replaceAll('_', ' ')
                        }}</span>
                    </div>
                    <footer
                        class="mt-3 flex items-center justify-between gap-2 border-t border-border/60 pt-3"
                    >
                        <span
                            class="flex min-w-0 items-center gap-1.5 text-[11px] text-muted-foreground"
                        >
                            <span
                                v-if="ticket.assignee"
                                class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[8px] font-semibold text-primary"
                                >{{ initials(ticket.assignee.name) }}</span
                            >
                            <UserRound v-else class="size-3.5" />
                            <span class="truncate">{{
                                ticket.assignee?.name ?? 'Unassigned'
                            }}</span>
                        </span>
                        <span
                            v-if="ticket.due_date"
                            class="flex shrink-0 items-center gap-1 text-[10px] text-muted-foreground"
                            ><CalendarDays class="size-3" />{{
                                dueDate(ticket.due_date)
                            }}</span
                        >
                    </footer>
                </Link>
            </TransitionGroup>
            <div
                v-if="!column.tickets.data.length"
                class="flex min-h-28 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/80 text-muted-foreground/70"
            >
                <CircleDashed class="size-5" :stroke-width="1.3" />
                <p class="text-[11px]">No matching tickets</p>
            </div>
            <Pagination :links="column.tickets.links" />
        </section>
        <p v-if="!columns.length" class="panel empty w-full">
            No board stages are available.
        </p>
    </div>
</template>
