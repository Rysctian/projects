<script setup lang="ts">
import { ref } from 'vue';
import { Layers, Pencil } from '@lucide/vue';
import FormSurface from '@/components/astreia/FormSurface.vue';
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
const open = ref(false);
const form = useForm({
    name: props.module.name,
    status: props.module.status ?? 'active',
});
</script>
<template>
    <article
        class="flex items-center justify-between gap-3 rounded-lg border bg-background/40 p-4"
    >
        <div class="flex min-w-0 items-center gap-3">
            <Layers class="size-4 shrink-0 text-muted-foreground" />
            <div>
                <p class="text-sm font-medium">{{ module.name }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    {{ module.code }} · {{ module.status }}
                </p>
            </div>
        </div>
        <FormSurface
            v-if="editable"
            v-model:open="open"
            title="Edit module"
            description="Keep the module name and availability up to date."
            :busy="form.processing"
        >
            <template #trigger
                ><Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`Edit ${module.name}`"
                    ><Pencil class="size-3.5" /></Button
            ></template>
            <form
                class="space-y-4"
                @submit.prevent="
                    form.patch(`${base}/modules/${module.id}`, {
                        preserveScroll: true,
                        onSuccess: () => {
                            open = false;
                        },
                    })
                "
            >
                <p class="font-mono text-xs text-muted-foreground">
                    {{ module.code }}
                </p>
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
        </FormSurface>
    </article>
</template>
