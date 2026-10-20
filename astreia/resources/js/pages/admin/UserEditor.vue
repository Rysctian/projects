<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormErrors from '@/components/astreia/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        is_system_admin: boolean;
    };
}>();
const form = useForm({
    is_active: props.user.is_active,
    is_system_admin: props.user.is_system_admin,
});
const invitation = useForm({});
function update(field: 'is_active' | 'is_system_admin') {
    form.is_active = props.user.is_active;
    form.is_system_admin = props.user.is_system_admin;
    form[field] = !form[field];
    form.patch(`/admin/users/${props.user.id}`, { preserveScroll: true });
}
</script>
<template>
    <article class="space-y-3 border-t px-4 py-4 first:border-t-0 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold">{{ user.name }}</h2>
                    <Badge v-if="user.is_system_admin" variant="outline"
                        >System Admin</Badge
                    >
                    <Badge
                        :variant="user.is_active ? 'secondary' : 'outline'"
                        >{{ user.is_active ? 'Active' : 'Inactive' }}</Badge
                    >
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ user.email }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="user.is_active"
                    size="sm"
                    variant="outline"
                    :disabled="invitation.processing"
                    @click="
                        invitation.post(`/admin/users/${user.id}/invitation`, {
                            preserveScroll: true,
                        })
                    "
                    >{{
                        invitation.processing ? 'Sending…' : 'Resend setup link'
                    }}</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="form.processing"
                    @click="update('is_system_admin')"
                    >{{
                        user.is_system_admin
                            ? 'Remove admin status'
                            : 'Make System Admin'
                    }}</Button
                ><Button
                    size="sm"
                    :variant="user.is_active ? 'outline' : 'default'"
                    :disabled="form.processing"
                    @click="update('is_active')"
                    >{{ user.is_active ? 'Deactivate' : 'Reactivate' }}</Button
                >
            </div>
        </div>
        <FormErrors :errors="form.errors" />
        <FormErrors :errors="invitation.errors" />
        <p v-if="invitation.recentlySuccessful" role="status" class="muted">
            Password setup link sent.
        </p>
        <p v-if="form.recentlySuccessful" role="status" class="muted">
            Account updated.
        </p>
    </article>
</template>
