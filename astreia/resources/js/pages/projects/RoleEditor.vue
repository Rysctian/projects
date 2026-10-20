<script setup lang="ts">
import { ref } from 'vue';
import { ShieldCheck, Plus } from '@lucide/vue';
import FormSurface from '@/components/astreia/FormSurface.vue';
import { useForm } from '@inertiajs/vue3';
import Field from '@/components/astreia/Field.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import type { Role } from '@/types/astreia';
const props = defineProps<{
    base: string;
    role?: Role;
    grantable: string[];
    editable: boolean;
}>();
const open = ref(false);
const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    permissions:
        props.role?.permissions.map((permission) => permission.key) ??
        ([] as string[]),
    is_active: props.role?.is_active ?? true,
});
function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.role) form.reset();
            open.value = false;
        },
    };
    if (props.role) form.patch(`${props.base}/roles/${props.role.id}`, options);
    else form.post(`${props.base}/roles`, options);
}
</script>
<template>
    <FormSurface
        v-model:open="open"
        :title="role ? role.name : 'Create role'"
        description="Choose the permissions this role grants within the project."
        mode="drawer"
        :busy="form.processing"
    >
        <template #trigger
            ><Button
                variant="outline"
                class="h-auto w-full justify-start gap-3 p-4 text-left"
            >
                <component
                    :is="role ? ShieldCheck : Plus"
                    class="size-4 shrink-0 text-primary"
                />
                <span class="flex-1"
                    >{{ role?.name ?? 'Create role'
                    }}<span
                        v-if="role"
                        class="mt-1 block text-xs font-normal text-muted-foreground"
                        >{{ role.permissions.length }} permissions</span
                    ></span
                >
                <Badge v-if="role && !role.is_active" variant="secondary"
                    >Inactive</Badge
                >
            </Button></template
        >
        <form class="mt-4 space-y-4" @submit.prevent="save">
            <FormErrors :errors="form.errors" />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Role name"
                    ><Input
                        v-model="form.name"
                        required
                        :disabled="!editable" /></Field
                ><Field label="Description"
                    ><Input v-model="form.description" :disabled="!editable"
                /></Field>
            </div>
            <label class="flex items-center gap-2 text-sm"
                ><input
                    v-model="form.is_active"
                    type="checkbox"
                    :disabled="!editable"
                />Active role</label
            >
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Permissions</legend>
                <p class="mb-3 text-xs text-muted-foreground">
                    You can grant only permissions available to your account.
                </p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="key in grantable"
                        :key="key"
                        class="flex items-center gap-2 text-xs"
                        ><input
                            v-model="form.permissions"
                            :value="key"
                            type="checkbox"
                            :disabled="!editable"
                        />{{ key }}</label
                    >
                </div>
                <p
                    v-for="key in form.permissions.filter(
                        (value) => !grantable.includes(value),
                    )"
                    :key="key"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    {{ key }} · Existing permission outside your grantable
                    access
                </p>
            </fieldset>
            <Button v-if="editable" size="sm" :disabled="form.processing">{{
                form.processing ? 'Saving…' : role ? 'Save role' : 'Create role'
            }}</Button
            ><span
                v-if="form.recentlySuccessful"
                role="status"
                class="ml-3 text-xs text-muted-foreground"
                >Role saved.</span
            >
        </form>
    </FormSurface>
</template>
