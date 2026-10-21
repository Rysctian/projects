<script setup lang="ts">
import { computed } from 'vue';
import Field from '@/components/astreia/Field.vue';
import { Input } from '@/components/ui/input';
import type {
    CatalogOption,
    Module,
    Person,
    PriorityOption,
} from '@/types/astreia';
const props = defineProps<{
    form: {
        title: string;
        description: string;
        category: string;
        priority: string;
        work_type_id?: number | null;
        priority_id?: number | null;
        assigned_to: number | null;
        module_id: number | null;
        estimate_minutes: number | null;
        due_date: string;
        acceptance_criteria: string[];
    };
    categories: string[];
    priorities: string[];
    modules: Module[];
    assignees: Person[];
    editable?: boolean;
    assignable?: boolean;
    incomplete?: boolean;
    core?: boolean;
    workTypes?: CatalogOption[];
    priorityOptions?: PriorityOption[];
}>();
const activeWorkTypes = computed(() =>
    (props.workTypes ?? []).filter((item) => item.is_active),
);
const activePriorities = computed(() =>
    (props.priorityOptions ?? [])
        .filter((item) => item.is_active)
        .sort((a, b) => a.rank - b.rank),
);
const criteria = computed({
    get: () => props.form.acceptance_criteria.join('\n'),
    set: (value: string) => {
        props.form.acceptance_criteria = value.split('\n');
    },
});
</script>
<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <Field :label="core ? 'Summary' : 'Title'" class="sm:col-span-2"
            ><Input
                v-model="form.title"
                :aria-label="core ? 'Summary' : 'Title'"
                :disabled="!editable"
                :required="core || !incomplete"
                placeholder="A clear summary of the work"
        /></Field>
        <Field label="Description" class="sm:col-span-2">
            <textarea
                v-model="form.description"
                aria-label="Description"
                :disabled="!editable"
                :required="core || !incomplete"
                class="control"
                placeholder="Context, expected outcome, and details"
            />
        </Field>
        <template v-if="core">
            <Field label="Work type">
                <select
                    v-model="form.work_type_id"
                    aria-label="Work type"
                    :disabled="!editable"
                    required
                    class="control"
                >
                    <option :value="null" disabled>Select work type</option>
                    <option
                        v-if="
                            form.work_type_id &&
                            !activeWorkTypes.some(
                                (item) => item.id === form.work_type_id,
                            )
                        "
                        :value="form.work_type_id"
                        disabled
                    >
                        Retired work type — choose an active type
                    </option>
                    <option
                        v-for="item in activeWorkTypes"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </Field>
            <Field label="Priority">
                <select
                    v-model="form.priority_id"
                    aria-label="Priority"
                    :disabled="!editable"
                    required
                    class="control"
                >
                    <option :value="null" disabled>Select priority</option>
                    <option
                        v-if="
                            form.priority_id &&
                            !activePriorities.some(
                                (item) => item.id === form.priority_id,
                            )
                        "
                        :value="form.priority_id"
                        disabled
                    >
                        Retired priority — choose an active priority
                    </option>
                    <option
                        v-for="item in activePriorities"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </Field>
        </template>
        <Field v-if="!core" label="Category"
            ><select
                v-model="form.category"
                aria-label="Category"
                :disabled="!editable || incomplete"
                class="control"
            >
                <option v-for="value in categories" :key="value" :value="value">
                    {{ value.replaceAll('_', ' ') }}
                </option>
            </select></Field
        >
        <Field v-if="!core" label="Priority"
            ><select
                v-model="form.priority"
                aria-label="Priority"
                :disabled="!editable || incomplete"
                class="control"
            >
                <option v-for="value in priorities" :key="value" :value="value">
                    {{ value }}
                </option>
            </select></Field
        >
        <Field label="Assignee"
            ><select
                v-model="form.assigned_to"
                aria-label="Assignee"
                :disabled="!assignable"
                class="control"
            >
                <option :value="null">Unassigned</option>
                <option
                    v-if="
                        core &&
                        form.assigned_to &&
                        !assignees.some((user) => user.id === form.assigned_to)
                    "
                    :value="form.assigned_to"
                    disabled
                >
                    Current assignee is no longer eligible
                </option>
                <option
                    v-for="user in assignees"
                    :key="user.id"
                    :value="user.id"
                >
                    {{ user.name }}
                </option>
            </select></Field
        >
        <Field :label="core ? 'Component' : 'Module'"
            ><select
                v-model="form.module_id"
                :aria-label="core ? 'Component' : 'Module'"
                :disabled="!editable"
                class="control"
            >
                <option :value="null">
                    {{ core ? 'No component' : 'No module' }}
                </option>
                <option
                    v-for="module in modules"
                    :key="module.id"
                    :value="module.id"
                >
                    {{ module.name }}
                </option>
            </select></Field
        >
        <Field
            label="Estimate (minutes)"
            hint="Optional. An eight-hour workday is 480 minutes."
            ><input
                v-model.number="form.estimate_minutes"
                aria-label="Estimate (minutes)"
                :disabled="!editable"
                type="number"
                min="0"
                step="1"
                class="control"
                placeholder="Not estimated"
        /></Field>
        <Field label="Due date"
            ><input
                v-model="form.due_date"
                aria-label="Due date"
                :disabled="!editable"
                type="date"
                class="control"
        /></Field>
        <Field
            label="Acceptance criteria"
            :hint="
                core
                    ? 'Optional. One criterion per line.'
                    : 'One criterion per line. Required for feature, enhancement, and bug work before work begins.'
            "
            class="sm:col-span-2"
        >
            <textarea
                v-model="criteria"
                aria-label="Acceptance criteria"
                :disabled="!editable"
                class="control"
                placeholder="Describe what must be true when this is done"
            />
        </Field>
    </div>
</template>
