<script setup lang="ts">
import { UserRound } from '@lucide/vue';
import FormSurface from '@/components/astreia/FormSurface.vue';
import { useForm } from '@inertiajs/vue3';
import FormErrors from '@/components/astreia/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { Member, Role } from '@/types/astreia';
const props = defineProps<{
    base: string;
    member: Member;
    roles: Role[];
    grantable: string[];
    manageMembers: boolean;
    manageAccess: boolean;
}>();
const form = useForm({
    role_ids: props.member.roles.map((role) => role.id),
    is_active: props.member.is_active,
});
const overrides = useForm({
    overrides: props.member.overrides.map((override) => ({
        key: override.permission.key,
        effect: override.effect,
    })),
});
</script>
<template>
    <FormSurface
        :title="member.user.name"
        description="Manage this person's project membership, roles, and permission overrides."
        mode="drawer"
    >
        <template #trigger
            ><Button
                variant="outline"
                class="h-auto w-full justify-start gap-3 p-4 text-left"
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/8 text-primary"
                    ><UserRound class="size-4"
                /></span>
                <span class="min-w-0 flex-1"
                    ><span class="block truncate">{{ member.user.name }}</span
                    ><span
                        class="mt-1 block truncate text-xs font-normal text-muted-foreground"
                        >{{ member.user.email }}</span
                    ></span
                >
                <Badge :variant="member.is_active ? 'secondary' : 'outline'">{{
                    member.is_active
                        ? member.roles.map((role) => role.name).join(', ') ||
                          'No roles'
                        : 'Inactive'
                }}</Badge>
            </Button></template
        >
        <form
            class="mt-4 space-y-4"
            @submit.prevent="
                form.patch(`${base}/members/${member.id}`, {
                    preserveScroll: true,
                })
            "
        >
            <FormErrors :errors="form.errors" />
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Project roles</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="role in roles"
                        :key="role.id"
                        class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="form.role_ids"
                            :value="role.id"
                            type="checkbox"
                            :disabled="!manageMembers"
                        />{{ role.name
                        }}<span v-if="!role.is_active" class="muted"
                            >(inactive)</span
                        ></label
                    >
                </div>
            </fieldset>
            <label class="flex items-center gap-2 text-sm"
                ><input
                    v-model="form.is_active"
                    type="checkbox"
                    :disabled="!manageMembers"
                />Active project membership</label
            >
            <Button
                v-if="manageMembers"
                size="sm"
                :disabled="form.processing"
                >{{ form.processing ? 'Saving…' : 'Save membership' }}</Button
            ><span
                v-if="form.recentlySuccessful"
                class="ml-3 text-xs text-muted-foreground"
                role="status"
                >Membership saved.</span
            >
        </form>
        <form
            v-if="manageAccess || member.overrides.length"
            class="mt-5 space-y-3 border-t pt-4"
            @submit.prevent="
                overrides.put(`${base}/members/${member.id}/overrides`, {
                    preserveScroll: true,
                })
            "
        >
            <h3 class="text-sm font-medium">Permission overrides</h3>
            <p class="muted">
                Explicit deny takes precedence over role grants. Saving replaces
                this member’s full override list.
            </p>
            <FormErrors :errors="overrides.errors" />
            <div
                v-for="(override, index) in overrides.overrides"
                :key="index"
                class="flex flex-wrap gap-2"
            >
                <select
                    v-model="override.key"
                    :disabled="!manageAccess"
                    aria-label="Override permission"
                    class="control min-w-48 flex-1"
                >
                    <option value="" disabled>Select permission</option>
                    <option
                        v-if="override.key && !grantable.includes(override.key)"
                        :value="override.key"
                    >
                        {{ override.key }} (existing)
                    </option>
                    <option v-for="key in grantable" :key="key" :value="key">
                        {{ key }}
                    </option>
                </select>
                <select
                    v-model="override.effect"
                    :disabled="!manageAccess"
                    aria-label="Override effect"
                    class="control !w-28"
                >
                    <option value="allow">Allow</option>
                    <option value="deny">Deny</option></select
                ><Button
                    v-if="manageAccess"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="overrides.overrides.splice(index, 1)"
                    >Remove</Button
                >
            </div>
            <p v-if="!overrides.overrides.length" class="muted">
                No explicit overrides. Access follows active roles.
            </p>
            <div v-if="manageAccess" class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="!grantable.length"
                    @click="
                        overrides.overrides.push({ key: '', effect: 'allow' })
                    "
                    >Add override</Button
                ><Button size="sm" :disabled="overrides.processing">{{
                    overrides.processing ? 'Saving…' : 'Save overrides'
                }}</Button
                ><span
                    v-if="overrides.recentlySuccessful"
                    class="self-center text-xs text-muted-foreground"
                    role="status"
                    >Overrides saved.</span
                >
            </div>
        </form>
    </FormSurface>
</template>
