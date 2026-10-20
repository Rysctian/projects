<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { UserPlus } from '@lucide/vue';
import PageFrame from '@/components/astreia/PageFrame.vue';
import Field from '@/components/astreia/Field.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import Pagination from '@/components/astreia/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import UserEditor from './UserEditor.vue';
import type { Paginated } from '@/types/astreia';
defineProps<{
    users: Paginated<{
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        is_system_admin: boolean;
    }>;
}>();
const creating = ref(false);
const form = useForm({ name: '', email: '' });
</script>
<template>
    <PageFrame
        title="Users"
        eyebrow="Administration"
        description="Manage workspace accounts. Project roles control each member’s access within a project."
    >
        <template #actions
            ><Button @click="creating = !creating"
                ><UserPlus class="size-4" />Create account</Button
            ></template
        >
        <form
            v-if="creating"
            class="panel space-y-4"
            @submit.prevent="
                form.post('/admin/users', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        creating = false;
                    },
                })
            "
        >
            <h2 class="section-title">Create an account</h2>
            <FormErrors :errors="form.errors" />
            <div class="grid max-w-3xl gap-4 sm:grid-cols-2">
                <Field label="Full name"
                    ><Input
                        v-model="form.name"
                        required
                        autocomplete="name" /></Field
                ><Field label="Email"
                    ><Input
                        v-model="form.email"
                        required
                        type="email"
                        autocomplete="email"
                /></Field>
            </div>
            <p class="muted">
                A password setup notification will be sent to the new account.
                No initial password is displayed.
            </p>
            <div class="flex gap-2">
                <Button :disabled="form.processing">{{
                    form.processing ? 'Creating…' : 'Create & send setup link'
                }}</Button
                ><Button type="button" variant="ghost" @click="creating = false"
                    >Cancel</Button
                >
            </div>
        </form>
        <div class="overflow-hidden rounded-xl border">
            <UserEditor
                v-for="user in users.data"
                :key="user.id"
                :user="user"
            />
            <p v-if="!users.data.length" class="empty">
                No accounts to display.
            </p>
        </div>
        <Pagination :links="users.links" />
    </PageFrame>
</template>
