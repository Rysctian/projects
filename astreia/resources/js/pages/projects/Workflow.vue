<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowDown, ArrowUp, Plus } from '@lucide/vue';
import PageFrame from '@/components/astreia/PageFrame.vue';
import Field from '@/components/astreia/Field.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import type { Capabilities, Project, Workflow } from '@/types/astreia';
type DraftStage = {
    key: string;
    name: string;
    type: string;
    sort_order: number;
    is_initial: boolean;
    is_active: boolean;
};
type DraftTransition = {
    from_key: string;
    to_key: string;
    required_permission_key: string | null;
    requires_reason: boolean;
    requires_signoff: boolean;
};
const props = defineProps<{
    project: Project;
    workflows: Workflow[];
    permissionKeys: string[];
    stageTypes: string[];
    capabilities: Capabilities;
}>();
const base = `/projects/${props.project.id}`;
const defaults = [
    'intake',
    'ready',
    'work',
    'review',
    'verification',
    'pre_close',
    'closed',
];
const types = props.stageTypes.length ? props.stageTypes : defaults;
const form = useForm({
    workflow_id: undefined as number | undefined,
    stages: [] as DraftStage[],
    transitions: [] as DraftTransition[],
});
const publishForm = useForm({});
const source = ref('New workflow');
function load(workflow?: Workflow, clone = false) {
    form.clearErrors();
    form.workflow_id =
        workflow?.state === 'draft' && !clone ? workflow.id : undefined;
    source.value = workflow
        ? `${clone || workflow.state !== 'draft' ? 'New draft from' : 'Editing draft'} version ${workflow.version_number}`
        : 'New workflow';
    form.stages = workflow
        ? [...workflow.stages]
              .sort((a, b) => a.sort_order - b.sort_order)
              .map(
                  ({ key, name, type, sort_order, is_initial, is_active }) => ({
                      key,
                      name,
                      type,
                      sort_order,
                      is_initial,
                      is_active,
                  }),
              )
        : defaults.map((type, index) => ({
              key: type,
              name: type
                  .replaceAll('_', ' ')
                  .replace(/^./, (value) => value.toUpperCase()),
              type,
              sort_order: index,
              is_initial: index === 0,
              is_active: true,
          }));
    form.transitions = workflow
        ? workflow.transitions.map((transition) => ({
              from_key:
                  workflow.stages.find(
                      (stage) => stage.id === transition.from_stage_id,
                  )?.key ?? '',
              to_key:
                  workflow.stages.find(
                      (stage) => stage.id === transition.to_stage_id,
                  )?.key ?? '',
              required_permission_key:
                  transition.required_permission?.key ?? null,
              requires_reason: transition.requires_reason,
              requires_signoff: transition.requires_signoff,
          }))
        : defaults.slice(0, -1).map((type, index) => ({
              from_key: type,
              to_key: defaults[index + 1]!,
              required_permission_key: null,
              requires_reason: false,
              requires_signoff: false,
          }));
    form.defaults();
}
load(
    props.workflows.find((workflow) => workflow.state === 'draft') ??
        props.workflows.find(
            (workflow) => workflow.id === props.project.current_workflow_id,
        ),
);
function move(index: number, direction: number) {
    const target = index + direction;
    if (target < 0 || target >= form.stages.length) return;
    const stage = form.stages.splice(index, 1)[0]!;
    form.stages.splice(target, 0, stage);
    form.stages.forEach((item, order) => {
        item.sort_order = order;
    });
}
function save() {
    form.transform((data) => ({
        ...(data.workflow_id ? { workflow_id: data.workflow_id } : {}),
        stages: data.stages.map((stage, index) => ({
            ...stage,
            sort_order: index,
        })),
        transitions: data.transitions,
    })).post(`${base}/workflows/draft`, {
        preserveScroll: true,
        onSuccess: () => {
            const draft = form.workflow_id
                ? props.workflows.find(
                      (workflow) => workflow.id === form.workflow_id,
                  )
                : [...props.workflows]
                      .filter((workflow) => workflow.state === 'draft')
                      .sort((a, b) => b.version_number - a.version_number)[0];
            if (draft) load(draft);
        },
    });
}
function publish(id: number) {
    publishForm.post(`${base}/workflows/${id}/publish`, {
        preserveScroll: true,
        onSuccess: () => {
            if (form.workflow_id === id)
                load(
                    props.workflows.find((workflow) => workflow.id === id),
                    true,
                );
        },
    });
}
</script>
<template>
    <PageFrame
        title="Workflow"
        :eyebrow="project.code"
        :back="base"
        description="Configure stages and allowed transitions. Published versions stay with the tickets that use them."
    >
        <template #actions
            ><Badge variant="outline"
                >{{ workflows.length }} versions</Badge
            ></template
        >
        <FormErrors :errors="publishForm.errors" />
        <section
            v-if="capabilities['workflows.manage']"
            class="panel space-y-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Draft editor</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ source }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="load()"
                    >Start from template</Button
                >
            </div>
            <FormErrors :errors="form.errors" />
            <form class="space-y-6" @submit.prevent="save">
                <fieldset class="space-y-3">
                    <legend class="mb-3 text-sm font-semibold">Stages</legend>
                    <p class="muted">
                        Keep keys stable. Disabling a stage preserves its
                        history. Exactly one stage must be initial.
                    </p>
                    <div
                        v-for="(stage, index) in form.stages"
                        :key="index"
                        class="rounded-lg border p-3"
                    >
                        <div
                            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1.5fr_1fr_auto]"
                        >
                            <Field label="Stable key"
                                ><Input
                                    v-model="stage.key"
                                    required
                                    pattern="[a-z][a-z0-9_]{0,59}"
                                    placeholder="development_2" /></Field
                            ><Field label="Stage name"
                                ><Input v-model="stage.name" required /></Field
                            ><Field label="Semantic type"
                                ><select v-model="stage.type" class="control">
                                    <option
                                        v-for="type in types"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ type.replaceAll('_', ' ') }}
                                    </option>
                                </select></Field
                            >
                            <div class="flex items-end gap-1 pb-0.5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :disabled="index === 0"
                                    :aria-label="`Move ${stage.name || 'stage'} up`"
                                    @click="move(index, -1)"
                                    ><ArrowUp class="size-4" /></Button
                                ><Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :disabled="index === form.stages.length - 1"
                                    :aria-label="`Move ${stage.name || 'stage'} down`"
                                    @click="move(index, 1)"
                                    ><ArrowDown class="size-4"
                                /></Button>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-5 text-xs">
                            <label class="flex items-center gap-2"
                                ><input
                                    :checked="stage.is_initial"
                                    type="radio"
                                    name="initial-stage"
                                    @change="
                                        form.stages.forEach(
                                            (item) =>
                                                (item.is_initial =
                                                    item === stage),
                                        )
                                    "
                                />Initial stage</label
                            ><label class="flex items-center gap-2"
                                ><input
                                    v-model="stage.is_active"
                                    type="checkbox"
                                />Active</label
                            >
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="
                            form.stages.push({
                                key: `stage_${form.stages.length + 1}`,
                                name: '',
                                type: 'work',
                                sort_order: form.stages.length,
                                is_initial: false,
                                is_active: true,
                            })
                        "
                        ><Plus class="size-4" />Add stage</Button
                    >
                </fieldset>
                <fieldset class="space-y-3">
                    <legend class="mb-3 text-sm font-semibold">
                        Allowed transitions
                    </legend>
                    <p class="muted">
                        Connect stages explicitly, including return paths.
                        Closure checks always apply.
                    </p>
                    <div
                        v-for="(transition, index) in form.transitions"
                        :key="index"
                        class="space-y-3 rounded-lg border p-3"
                    >
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Field label="From stage"
                                ><select
                                    v-model="transition.from_key"
                                    required
                                    class="control"
                                >
                                    <option value="" disabled>
                                        Select stage
                                    </option>
                                    <option
                                        v-for="stage in form.stages"
                                        :key="stage.key"
                                        :value="stage.key"
                                    >
                                        {{ stage.name || stage.key }}
                                    </option>
                                </select></Field
                            ><Field label="To stage"
                                ><select
                                    v-model="transition.to_key"
                                    required
                                    class="control"
                                >
                                    <option value="" disabled>
                                        Select stage
                                    </option>
                                    <option
                                        v-for="stage in form.stages"
                                        :key="stage.key"
                                        :value="stage.key"
                                    >
                                        {{ stage.name || stage.key }}
                                    </option>
                                </select></Field
                            ><Field label="Additional required permission"
                                ><select
                                    v-model="transition.required_permission_key"
                                    class="control"
                                >
                                    <option :value="null">
                                        No additional permission
                                    </option>
                                    <option
                                        v-for="key in permissionKeys"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ key }}
                                    </option>
                                </select></Field
                            >
                        </div>
                        <div class="flex flex-wrap items-center gap-5 text-xs">
                            <label class="flex items-center gap-2"
                                ><input
                                    v-model="transition.requires_reason"
                                    type="checkbox"
                                />Require reason</label
                            ><label class="flex items-center gap-2"
                                ><input
                                    v-model="transition.requires_signoff"
                                    type="checkbox"
                                />Require manual sign-off</label
                            ><Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="ml-auto"
                                @click="form.transitions.splice(index, 1)"
                                >Remove transition</Button
                            >
                        </div>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="
                            form.transitions.push({
                                from_key: form.stages[0]?.key ?? '',
                                to_key: '',
                                required_permission_key: null,
                                requires_reason: false,
                                requires_signoff: false,
                            })
                        "
                        ><Plus class="size-4" />Add transition</Button
                    >
                </fieldset>
                <div class="flex flex-wrap items-center gap-3 border-t pt-4">
                    <Button :disabled="form.processing">{{
                        form.processing ? 'Saving…' : 'Save draft'
                    }}</Button>
                    <p class="muted">
                        Save, review the version below, then publish.
                    </p>
                    <span
                        v-if="form.recentlySuccessful"
                        role="status"
                        class="muted"
                        >Draft saved.</span
                    >
                </div>
            </form>
        </section>
        <section class="space-y-3">
            <h2 class="font-semibold">Version history</h2>
            <p v-if="!workflows.length" class="panel empty">
                No workflow versions yet.
            </p>
            <article
                v-for="workflow in [...workflows].sort(
                    (a, b) => b.version_number - a.version_number,
                )"
                :key="workflow.id"
                class="panel space-y-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold">
                            Version {{ workflow.version_number }}
                        </h3>
                        <Badge
                            :variant="
                                workflow.state === 'published'
                                    ? 'default'
                                    : 'secondary'
                            "
                            >{{ workflow.state }}</Badge
                        ><Badge
                            v-if="project.current_workflow_id === workflow.id"
                            variant="outline"
                            >Current for new tickets</Badge
                        >
                    </div>
                    <div
                        v-if="capabilities['workflows.manage']"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="load(workflow, workflow.state !== 'draft')"
                            >{{
                                workflow.state === 'draft'
                                    ? 'Load draft'
                                    : 'Clone into new draft'
                            }}</Button
                        ><Button
                            v-if="workflow.state === 'draft'"
                            size="sm"
                            :disabled="
                                publishForm.processing ||
                                (form.workflow_id === workflow.id &&
                                    form.isDirty)
                            "
                            @click="publish(workflow.id)"
                            >Publish saved draft</Button
                        >
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="stage in [...workflow.stages].sort(
                            (a, b) => a.sort_order - b.sort_order,
                        )"
                        :key="stage.id"
                        variant="outline"
                        :class="!stage.is_active ? 'opacity-50' : ''"
                        >{{ stage.is_initial ? '↳ ' : '' }}{{ stage.name }} ·
                        {{ stage.type.replaceAll('_', ' ')
                        }}{{ !stage.is_active ? ' · disabled' : '' }}</Badge
                    >
                </div>
                <details>
                    <summary class="text-muted-foreground">
                        {{ workflow.transitions.length }} allowed transitions
                    </summary>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li
                            v-for="transition in workflow.transitions"
                            :key="transition.id"
                            class="flex flex-wrap gap-2"
                        >
                            <span
                                >{{
                                    workflow.stages.find(
                                        (stage) =>
                                            stage.id ===
                                            transition.from_stage_id,
                                    )?.name ?? 'Unknown stage'
                                }}
                                →
                                {{
                                    workflow.stages.find(
                                        (stage) =>
                                            stage.id === transition.to_stage_id,
                                    )?.name ?? 'Unknown stage'
                                }}</span
                            ><span class="text-xs text-muted-foreground">{{
                                [
                                    transition.required_permission?.key,
                                    transition.requires_reason
                                        ? 'reason required'
                                        : '',
                                    transition.requires_signoff
                                        ? 'manual sign-off required'
                                        : '',
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}</span>
                        </li>
                    </ul>
                </details>
            </article>
        </section>
    </PageFrame>
</template>
