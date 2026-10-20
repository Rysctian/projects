<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormErrors from '@/components/astreia/FormErrors.vue';
import Field from '@/components/astreia/Field.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Module } from '@/types/astreia';
const props = defineProps<{
    base: string;
    module: Module;
    editable: boolean;
}>();
const form = useForm({
    name: props.module.name,
    status: props.module.status ?? 'active',
});
</script>
<template>
    <form
        class="space-y-3 rounded-lg border p-4"
        @submit.prevent="
            form.patch(`${base}/modules/${module.id}`, { preserveScroll: true })
        "
    >
        <p class="font-mono text-xs text-muted-foreground">{{ module.code }}</p>
        <FormErrors :errors="form.errors" />
        <div class="flex flex-wrap items-end gap-3">
            <Field label="Module name" class="min-w-40 flex-1"
                ><Input
                    v-model="form.name"
                    required
                    :disabled="!editable" /></Field
            ><Field label="Status"
                ><select
                    v-model="form.status"
                    class="control"
                    :disabled="!editable"
                >
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                </select></Field
            ><Button
                v-if="editable"
                variant="outline"
                size="sm"
                :disabled="form.processing"
                >Save</Button
            >
        </div>
        <p v-if="form.recentlySuccessful" class="muted" role="status">
            Module saved.
        </p>
    </form>
</template>
