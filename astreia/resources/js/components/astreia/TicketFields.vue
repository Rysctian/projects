<script setup lang="ts">
import { computed } from 'vue';
import Field from '@/components/astreia/Field.vue';
import { Input } from '@/components/ui/input';
import type { Module, Person } from '@/types/astreia';
const props = defineProps<{
    form: {
        title: string;
        description: string;
        category: string;
        priority: string;
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
}>();
const criteria = computed({
    get: () => props.form.acceptance_criteria.join('\n'),
    set: (value: string) => {
        props.form.acceptance_criteria = value.split('\n');
    },
});
</script>
<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <Field label="Title" class="sm:col-span-2"
            ><Input
                v-model="form.title"
                :disabled="!editable"
                :required="!incomplete"
                placeholder="A clear summary of the work"
        /></Field>
        <Field label="Description" class="sm:col-span-2">
            <textarea
                v-model="form.description"
                :disabled="!editable"
                :required="!incomplete"
                class="control"
                placeholder="Context, expected outcome, and details"
            />
        </Field>
        <Field label="Category"
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
        <Field label="Priority"
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
                    v-for="user in assignees"
                    :key="user.id"
                    :value="user.id"
                >
                    {{ user.name }}
                </option>
            </select></Field
        >
        <Field label="Module"
            ><select
                v-model="form.module_id"
                aria-label="Module"
                :disabled="!editable"
                class="control"
            >
                <option :value="null">No module</option>
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
                :disabled="!editable"
                type="date"
                class="control"
        /></Field>
        <Field
            label="Acceptance criteria"
            hint="One criterion per line. Required for feature, enhancement, and bug work before work begins."
            class="sm:col-span-2"
        >
            <textarea
                v-model="criteria"
                :disabled="!editable"
                class="control"
                placeholder="Describe what must be true when this is done"
            />
        </Field>
    </div>
</template>
