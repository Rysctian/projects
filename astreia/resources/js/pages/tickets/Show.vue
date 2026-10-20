<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Paperclip, RefreshCw, Pencil } from '@lucide/vue';
import PageFrame from '@/components/astreia/PageFrame.vue';
import Field from '@/components/astreia/Field.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import TicketFields from '@/components/astreia/TicketFields.vue';
import TicketBadges from '@/components/astreia/TicketBadges.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import TicketActions from './TicketActions.vue';
import { activitySummary } from './activity';
import type {
    Activity,
    Capabilities,
    Label,
    Module,
    Person,
    Project,
    Signoff,
    Ticket,
    TicketTransition,
} from '@/types/astreia';
const props = defineProps<{
    project: Project;
    ticket: Ticket;
    transitions: TicketTransition[];
    activities: Activity[];
    comments: {
        id: number;
        body: string;
        created_at: string;
        author: { name: string };
    }[];
    signoffs: Signoff[];
    attachments: {
        id: number;
        original_filename: string;
        size_bytes: number;
    }[];
    eligibleAssignees: Person[];
    modules: Module[];
    categories: string[];
    priorities: string[];
    capabilities: Capabilities;
    labels: Label[];
    uploadsEnabled: boolean;
}>();
const base = `/projects/${props.project.id}/tickets/${props.ticket.id}`;
const editing = ref(false);
const tab = ref<'comments' | 'activity' | 'verification'>('comments');
function values() {
    return {
        lock_version: props.ticket.lock_version,
        title: props.ticket.title ?? '',
        description: props.ticket.description ?? '',
        category: props.ticket.category,
        priority: props.ticket.priority,
        assigned_to: props.ticket.assigned_to,
        module_id: props.ticket.module_id,
        estimate_minutes: props.ticket.estimate_minutes,
        due_date: props.ticket.due_date?.slice(0, 10) ?? '',
        acceptance_criteria: [...props.ticket.acceptance_criteria],
        label_ids: props.ticket.labels.map((label) => label.id),
    };
}
const form = useForm(values());
const comment = useForm({ body: '' });
const upload = useForm({ file: null as File | null });
const fileInput = ref<HTMLInputElement | null>(null);
const activityRows = computed(() =>
    props.activities.map((activity) => ({
        ...activity,
        ...activitySummary(activity.activity_type, activity.payload),
    })),
);
const canEdit = computed(() => !!props.capabilities['tickets.edit']);
const canAssign = computed(() => !!props.capabilities['tickets.assign']);
function sync() {
    Object.assign(form, values());
    form.defaults(values());
}
watch(
    () => props.ticket.lock_version,
    () => {
        if (!form.isDirty) sync();
    },
);
function save() {
    form.transform((data) => {
        if (!canEdit.value)
            return {
                lock_version: data.lock_version,
                assigned_to: data.assigned_to,
            };
        const { assigned_to, ...fields } = data;
        return {
            ...fields,
            ...(canAssign.value ? { assigned_to } : {}),
            estimate_minutes:
                data.estimate_minutes === null ||
                String(data.estimate_minutes) === ''
                    ? null
                    : Number(data.estimate_minutes),
            acceptance_criteria: data.acceptance_criteria
                .map((value) => value.trim())
                .filter(Boolean),
        };
    }).patch(base, {
        preserveScroll: true,
        onSuccess: () => {
            sync();
            editing.value = false;
        },
    });
}
function attach() {
    upload.post(`${base}/attachments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            upload.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
function selectFile(event: Event) {
    upload.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function date(value: string) {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}
function size(bytes: number) {
    return bytes < 1024
        ? `${bytes} B`
        : bytes < 1024 * 1024
          ? `${(bytes / 1024).toFixed(1)} KB`
          : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>
<template>
    <PageFrame
        :title="ticket.title || 'Untitled urgent intake'"
        :eyebrow="ticket.key"
        :back="`/projects/${project.id}`"
        :back-label="project.name"
    >
        <template #actions
            ><Button variant="outline" size="sm" as-child
                ><Link :href="base" :preserve-state="false"
                    ><RefreshCw class="size-4" />Reload</Link
                ></Button
            ><Button
                v-if="canEdit || canAssign"
                size="sm"
                @click="editing = !editing"
                ><Pencil class="size-4" />{{
                    editing
                        ? 'View ticket'
                        : canEdit
                          ? 'Edit ticket'
                          : 'Change assignee'
                }}</Button
            ></template
        >
        <div class="flex flex-wrap items-center gap-2">
            <Badge>{{ ticket.stage.name }}</Badge
            ><TicketBadges v-bind="ticket" /><span
                class="text-xs text-muted-foreground"
                >Workflow v{{ ticket.workflow.version_number }} · Revision
                {{ ticket.revision_number }}</span
            >
        </div>
        <div
            v-if="ticket.is_intake_stub"
            class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm"
        >
            Incomplete urgent intake. Add the actual title, description, and
            required details before moving this ticket forward.
        </div>
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0 space-y-6">
                <form
                    v-if="editing && (canEdit || canAssign)"
                    class="panel space-y-4"
                    @submit.prevent="save"
                >
                    <h2 class="section-title">
                        {{ canEdit ? 'Ticket details' : 'Assignment' }}
                    </h2>
                    <FormErrors
                        :errors="form.errors"
                        :reload-url="base"
                    /><TicketFields
                        :form="form"
                        :categories="categories"
                        :priorities="priorities"
                        :modules="modules"
                        :assignees="eligibleAssignees"
                        :editable="canEdit"
                        :assignable="canAssign"
                        :incomplete="ticket.is_intake_stub"
                    />
                    <fieldset v-if="canEdit && labels.length">
                        <legend class="mb-2 text-sm font-medium">Labels</legend>
                        <div class="flex flex-wrap gap-4">
                            <label
                                v-for="label in labels"
                                :key="label.id"
                                class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="form.label_ids"
                                    type="checkbox"
                                    :value="label.id"
                                />{{ label.name }}</label
                            >
                        </div>
                    </fieldset>
                    <div class="flex gap-2">
                        <Button :disabled="form.processing">{{
                            form.processing ? 'Saving…' : 'Save changes'
                        }}</Button
                        ><Button
                            type="button"
                            variant="ghost"
                            @click="
                                sync();
                                form.clearErrors();
                                editing = false;
                            "
                            >Cancel</Button
                        >
                    </div>
                </form>
                <section v-else class="panel space-y-5">
                    <div>
                        <h2 class="section-title">Description</h2>
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">
                            {{
                                ticket.description ||
                                'Description has not been supplied yet.'
                            }}
                        </p>
                    </div>
                    <div class="border-t pt-5">
                        <h3 class="mb-3 text-sm font-semibold">
                            Acceptance criteria
                        </h3>
                        <ul
                            v-if="ticket.acceptance_criteria.length"
                            class="list-outside list-disc space-y-2 pl-5 text-sm"
                        >
                            <li
                                v-for="(
                                    criterion, index
                                ) in ticket.acceptance_criteria"
                                :key="index"
                                class="whitespace-pre-wrap"
                            >
                                {{ criterion }}
                            </li>
                        </ul>
                        <p v-else class="muted">
                            No acceptance criteria recorded.
                        </p>
                    </div>
                    <div
                        v-if="ticket.labels.length"
                        class="flex flex-wrap gap-2 border-t pt-4"
                    >
                        <Badge
                            v-for="label in ticket.labels"
                            :key="label.id"
                            variant="secondary"
                            >{{ label.name }}</Badge
                        >
                    </div>
                </section>
                <section
                    v-if="
                        ticket.completion_notes || ticket.documentation_impact
                    "
                    class="panel space-y-3"
                >
                    <h2 class="font-semibold">Completion record</h2>
                    <p
                        v-if="ticket.completion_notes"
                        class="text-sm whitespace-pre-wrap"
                    >
                        {{ ticket.completion_notes }}
                    </p>
                    <p v-if="ticket.documentation_impact" class="text-sm">
                        <span class="font-medium">Documentation:</span>
                        {{
                            ticket.documentation_impact === 'required'
                                ? 'Required · pending future documentation'
                                : 'Not required'
                        }}
                    </p>
                    <p
                        v-if="ticket.documentation_impact_note"
                        class="text-sm whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ ticket.documentation_impact_note }}
                    </p>
                </section>
                <section class="panel">
                    <div
                        role="tablist"
                        aria-label="Ticket collaboration"
                        class="mb-5 flex gap-1 border-b pb-3"
                    >
                        <Button
                            v-for="item in [
                                'comments',
                                'activity',
                                'verification',
                            ] as const"
                            :id="`tab-${item}`"
                            :key="item"
                            role="tab"
                            size="sm"
                            :variant="tab === item ? 'secondary' : 'ghost'"
                            :aria-selected="tab === item"
                            :aria-controls="`panel-${item}`"
                            @click="tab = item"
                            >{{ item.charAt(0).toUpperCase() + item.slice(1) }}
                            <span class="ml-1 text-xs text-muted-foreground">{{
                                item === 'comments'
                                    ? comments.length
                                    : item === 'activity'
                                      ? activities.length
                                      : signoffs.length
                            }}</span></Button
                        >
                    </div>
                    <div
                        v-if="tab === 'comments'"
                        id="panel-comments"
                        role="tabpanel"
                        aria-labelledby="tab-comments"
                        class="space-y-5"
                    >
                        <article
                            v-for="entry in comments"
                            :key="entry.id"
                            class="space-y-2 border-b pb-4"
                        >
                            <div
                                class="flex flex-wrap items-center gap-2 text-xs"
                            >
                                <span class="font-semibold">{{
                                    entry.author.name
                                }}</span
                                ><time
                                    :datetime="entry.created_at"
                                    class="text-muted-foreground"
                                    >{{ date(entry.created_at) }}</time
                                >
                            </div>
                            <p
                                class="text-sm leading-relaxed whitespace-pre-wrap"
                            >
                                {{ entry.body }}
                            </p>
                        </article>
                        <p v-if="!comments.length" class="muted">
                            No comments yet.
                        </p>
                        <form
                            v-if="capabilities['tickets.comment']"
                            class="space-y-3"
                            @submit.prevent="
                                comment.post(`${base}/comments`, {
                                    preserveScroll: true,
                                    onSuccess: () => comment.reset(),
                                })
                            "
                        >
                            <FormErrors :errors="comment.errors" /><Field
                                label="Add a comment"
                            >
                                <textarea
                                    v-model="comment.body"
                                    required
                                    class="control"
                                    placeholder="Share context, progress, or findings…"
                                /></Field
                            ><Button
                                size="sm"
                                :disabled="
                                    comment.processing || !comment.body.trim()
                                "
                                >Post comment</Button
                            >
                        </form>
                    </div>
                    <div
                        v-else-if="tab === 'activity'"
                        id="panel-activity"
                        role="tabpanel"
                        aria-labelledby="tab-activity"
                        class="space-y-5"
                    >
                        <article
                            v-for="entry in activityRows"
                            :key="entry.id"
                            class="border-l-2 pl-4"
                        >
                            <p class="text-sm font-medium">{{ entry.title }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ entry.actor?.name ?? 'System' }} ·
                                <time :datetime="entry.created_at">{{
                                    date(entry.created_at)
                                }}</time>
                            </p>
                            <p
                                v-for="(detail, index) in entry.details"
                                :key="index"
                                class="mt-2 text-sm whitespace-pre-wrap"
                            >
                                {{ detail }}
                            </p>
                        </article>
                        <p v-if="!activities.length" class="muted">
                            No activity recorded.
                        </p>
                    </div>
                    <div
                        v-else
                        id="panel-verification"
                        role="tabpanel"
                        aria-labelledby="tab-verification"
                        class="space-y-3"
                    >
                        <article
                            v-for="entry in signoffs"
                            :key="entry.id"
                            class="space-y-2 rounded-lg border p-3"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    :variant="
                                        entry.result === 'pass'
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                    >{{ entry.result }}</Badge
                                ><span class="text-sm font-medium uppercase">{{
                                    entry.kind
                                }}</span
                                ><span class="text-xs text-muted-foreground">{{
                                    entry.actor.name
                                }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Revision {{ entry.verified_revision }} · Visit
                                {{ entry.stage_visit }}
                                <span
                                    v-if="
                                        entry.verified_revision !==
                                        ticket.revision_number
                                    "
                                    >· Earlier revision — does not approve
                                    current requirements</span
                                ><span
                                    v-else-if="
                                        entry.stage_visit !== ticket.stage_visit
                                    "
                                    >· Earlier stage visit</span
                                >
                            </p>
                            <p
                                v-if="entry.note"
                                class="text-sm whitespace-pre-wrap"
                            >
                                {{ entry.note }}
                            </p>
                        </article>
                        <p v-if="!signoffs.length" class="muted">
                            No manual sign-offs recorded.
                        </p>
                    </div>
                </section>
                <section class="panel space-y-4">
                    <h2 class="font-semibold">Attachments</h2>
                    <ul v-if="attachments.length" class="divide-y">
                        <li
                            v-for="attachment in attachments"
                            :key="attachment.id"
                            class="flex items-center gap-3 py-3"
                        >
                            <Paperclip
                                class="size-4 shrink-0 text-muted-foreground"
                            /><a
                                :href="`${base}/attachments/${attachment.id}`"
                                class="min-w-0 flex-1 truncate text-sm underline-offset-4 hover:underline"
                                >{{ attachment.original_filename }}</a
                            ><span
                                class="shrink-0 text-xs text-muted-foreground"
                                >{{ size(attachment.size_bytes) }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="muted">No attachments.</p>
                    <form
                        v-if="uploadsEnabled && capabilities['tickets.attach']"
                        class="space-y-3"
                        @submit.prevent="attach"
                    >
                        <FormErrors :errors="upload.errors" /><Field
                            label="Attach evidence"
                            ><input
                                ref="fileInput"
                                type="file"
                                required
                                class="control"
                                @change="selectFile" /></Field
                        ><progress
                            v-if="upload.progress"
                            :value="upload.progress.percentage"
                            max="100"
                            class="w-full"
                            aria-label="Upload progress"
                        /><Button
                            size="sm"
                            variant="outline"
                            :disabled="upload.processing || !upload.file"
                            >{{
                                upload.processing
                                    ? 'Uploading…'
                                    : 'Upload attachment'
                            }}</Button
                        >
                    </form>
                    <p v-else-if="!uploadsEnabled" class="muted">
                        Uploads are disabled until file types and limits are
                        approved.
                    </p>
                </section>
            </div>
            <aside class="space-y-4">
                <section class="panel">
                    <h2 class="section-title">At a glance</h2>
                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-5 gap-y-3 text-sm"
                    >
                        <dt class="text-muted-foreground">Assignee</dt>
                        <dd class="text-right">
                            {{ ticket.assignee?.name ?? 'Unassigned' }}
                        </dd>
                        <dt class="text-muted-foreground">Reporter</dt>
                        <dd class="text-right">{{ ticket.reporter.name }}</dd>
                        <dt class="text-muted-foreground">Category</dt>
                        <dd class="text-right capitalize">
                            {{ ticket.category.replaceAll('_', ' ') }}
                        </dd>
                        <dt class="text-muted-foreground">Module</dt>
                        <dd class="text-right">
                            {{ ticket.module?.name ?? 'None' }}
                        </dd>
                        <dt class="text-muted-foreground">Due date</dt>
                        <dd class="text-right">
                            {{ ticket.due_date?.slice(0, 10) ?? 'Not set' }}
                        </dd>
                        <dt class="text-muted-foreground">Estimate</dt>
                        <dd class="text-right">
                            {{
                                ticket.estimate_minutes === null
                                    ? 'Not estimated'
                                    : `${ticket.estimate_minutes} min`
                            }}<span
                                v-if="
                                    ticket.estimate_minutes !== null &&
                                    ticket.estimate_minutes >= 480
                                "
                                class="block text-xs text-muted-foreground"
                                >{{
                                    (ticket.estimate_minutes / 480).toFixed(1)
                                }}
                                workdays</span
                            >
                        </dd>
                    </dl>
                </section>
                <TicketActions
                    :base="base"
                    :ticket="ticket"
                    :transitions="transitions"
                    :capabilities="capabilities"
                />
            </aside>
        </div>
    </PageFrame>
</template>
