<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import FormSurface from '@/components/astreia/FormSurface.vue';
import { Layers, Plus, Settings2, Tag, Users } from '@lucide/vue';
import PageFrame from '@/components/astreia/PageFrame.vue';
import Field from '@/components/astreia/Field.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import MemberEditor from './MemberEditor.vue';
import RoleEditor from './RoleEditor.vue';
import ModuleEditor from './ModuleEditor.vue';
import type {
    Capabilities,
    Label,
    Member,
    Module,
    Person,
    Project,
    Role,
} from '@/types/astreia';
const props = defineProps<{
    project: Project;
    members: Member[];
    roles: Role[];
    permissionKeys: string[];
    grantablePermissions: string[];
    users: (Person & { email: string })[];
    modules: Module[];
    labels: Label[];
    capabilities: Capabilities;
}>();
const detailsOpen = ref(false);
const moduleOpen = ref(false);
const labelOpen = ref(false);
const memberOpen = ref(false);
const base = `/projects/${props.project.id}`;
const projectForm = useForm({
    name: props.project.name,
    description: props.project.description ?? '',
    status: props.project.status,
});
const moduleForm = useForm({ code: '', name: '' });
const labelForm = useForm({ name: '' });
const memberForm = useForm({
    user_id: '' as number | '',
    role_ids: [] as number[],
});
</script>
<template>
    <PageFrame
        title="Project settings"
        :eyebrow="project.code"
        :back="base"
        description="Maintain project details, team access, and functional modules."
    >
        <template #actions
            ><Button
                v-if="capabilities['workflows.manage']"
                variant="outline"
                as-child
                ><Link :href="`${base}/workflows`"
                    >Configure workflow</Link
                ></Button
            ></template
        >
        <nav
            aria-label="Settings sections"
            class="flex flex-wrap gap-4 border-b pb-3 text-sm text-muted-foreground"
        >
            <a href="#details" class="hover:text-foreground">General</a
            ><a href="#modules" class="hover:text-foreground">Modules</a
            ><a href="#members" class="hover:text-foreground">Members</a
            ><a href="#roles" class="hover:text-foreground"
                >Roles & permissions</a
            >
            <a href="#labels" class="hover:text-foreground">Labels</a>
        </nav>
        <section id="details" class="panel scroll-mt-5">
            <div class="mb-5 flex items-center gap-3">
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-primary/8 text-primary"
                    ><Settings2 class="size-4"
                /></span>
                <div>
                    <h2 class="text-sm font-semibold">General</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ project.name }} · {{ project.status }}
                    </p>
                </div>
            </div>
            <p
                class="mb-4 max-w-2xl text-sm leading-relaxed text-muted-foreground"
            >
                {{
                    project.description || 'Add a little context for your team.'
                }}
            </p>
            <FormSurface
                v-if="capabilities['project.manage']"
                v-model:open="detailsOpen"
                title="Project details"
                description="Update the name, context, and status of your project."
                trigger="Edit project details"
                :busy="projectForm.processing"
            >
                <form
                    class="max-w-3xl space-y-4"
                    @submit.prevent="
                        projectForm.patch(base, {
                            preserveScroll: true,
                            onSuccess: () => {
                                detailsOpen = false;
                            },
                        })
                    "
                >
                    <FormErrors :errors="projectForm.errors" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field label="Project name"
                            ><Input
                                v-model="projectForm.name"
                                required
                                :disabled="
                                    !capabilities['project.manage']
                                " /></Field
                        ><Field label="Status"
                            ><select
                                v-model="projectForm.status"
                                :disabled="!capabilities['project.manage']"
                                class="control"
                            >
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select></Field
                        >
                    </div>
                    <Field label="Description">
                        <textarea
                            v-model="projectForm.description"
                            :disabled="!capabilities['project.manage']"
                            class="control"
                        />
                    </Field>
                    <p class="muted">
                        Project code {{ project.code }} is permanent. Archiving
                        preserves tickets and history.
                    </p>
                    <Button
                        v-if="capabilities['project.manage']"
                        size="sm"
                        :disabled="projectForm.processing"
                        >Save project</Button
                    ><span
                        v-if="projectForm.recentlySuccessful"
                        role="status"
                        class="ml-3 text-xs text-muted-foreground"
                        >Project saved.</span
                    >
                </form>
            </FormSurface>
        </section>
        <section id="modules" class="panel scroll-mt-5">
            <h2 class="section-title">
                <Layers class="mr-2 inline size-4 text-primary" />Modules
                <span class="text-muted-foreground"
                    >({{ modules.length }})</span
                >
            </h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <ModuleEditor
                    v-for="module in modules"
                    :key="module.id"
                    :base="base"
                    :module="module"
                    :editable="!!capabilities['modules.manage']"
                />
            </div>
            <p v-if="!modules.length" class="muted">No modules yet.</p>
            <div v-if="capabilities['modules.manage']" class="mt-4">
                <FormSurface
                    v-model:open="moduleOpen"
                    title="Add module"
                    description="Organize related work into a functional area."
                    :busy="moduleForm.processing"
                >
                    <template #trigger
                        ><Button variant="outline" size="sm"
                            ><Plus class="size-3.5" />Add module</Button
                        ></template
                    >
                    <form
                        class="mt-4 space-y-3"
                        @submit.prevent="
                            moduleForm.post(`${base}/modules`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    moduleForm.reset();
                                    moduleOpen = false;
                                },
                            })
                        "
                    >
                        <FormErrors :errors="moduleForm.errors" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <Field label="Code"
                                ><Input
                                    v-model="moduleForm.code"
                                    required
                                    placeholder="PAYROLL" /></Field
                            ><Field label="Name"
                                ><Input
                                    v-model="moduleForm.name"
                                    required
                                    placeholder="Payroll"
                            /></Field>
                        </div>
                        <Button size="sm" :disabled="moduleForm.processing"
                            >Add module</Button
                        >
                    </form>
                </FormSurface>
            </div>
        </section>
        <section id="labels" class="panel scroll-mt-5">
            <h2 class="section-title">
                <Tag class="mr-2 inline size-4 text-primary" />Labels
            </h2>
            <div v-if="labels.length" class="mb-4 flex flex-wrap gap-2">
                <Badge
                    v-for="label in labels"
                    :key="label.id"
                    variant="secondary"
                    >{{ label.name }}</Badge
                >
            </div>
            <p v-else class="muted mb-4">No project labels yet.</p>
            <FormSurface
                v-if="capabilities['labels.manage']"
                v-model:open="labelOpen"
                title="Add label"
                description="Create a shared label to make related tickets easier to find."
                trigger="Add label"
                :busy="labelForm.processing"
            >
                <form
                    v-if="capabilities['labels.manage']"
                    class="max-w-lg space-y-3"
                    @submit.prevent="
                        labelForm.post(`${base}/labels`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                labelForm.reset();
                                labelOpen = false;
                            },
                        })
                    "
                >
                    <FormErrors :errors="labelForm.errors" />
                    <div class="flex flex-wrap items-end gap-3">
                        <Field label="Label name" class="min-w-48 flex-1"
                            ><Input
                                v-model="labelForm.name"
                                required
                                placeholder="Client request" /></Field
                        ><Button size="sm" :disabled="labelForm.processing">{{
                            labelForm.processing ? 'Adding…' : 'Add label'
                        }}</Button>
                    </div>
                    <p
                        v-if="labelForm.recentlySuccessful"
                        class="muted"
                        role="status"
                    >
                        Label created.
                    </p>
                </form>
            </FormSurface>
        </section>
        <section id="members" class="panel scroll-mt-5">
            <h2 class="section-title">
                <Users class="mr-2 inline size-4 text-primary" />Project members
                <span class="text-muted-foreground"
                    >({{ members.length }})</span
                >
            </h2>
            <div class="space-y-3">
                <MemberEditor
                    v-for="member in members"
                    :key="member.id"
                    :base="base"
                    :member="member"
                    :roles="roles"
                    :grantable="grantablePermissions"
                    :manage-members="!!capabilities['members.manage']"
                    :manage-access="!!capabilities['access.manage']"
                />
            </div>
            <p v-if="!members.length" class="muted">No project members yet.</p>
            <div v-if="capabilities['members.manage']" class="mt-4">
                <FormSurface
                    v-model:open="memberOpen"
                    title="Add member"
                    description="Bring an existing account into this project and choose their roles."
                    :busy="memberForm.processing"
                >
                    <template #trigger
                        ><Button variant="outline" size="sm"
                            ><Plus class="size-3.5" />Add member</Button
                        ></template
                    >
                    <form
                        class="mt-4 space-y-4"
                        @submit.prevent="
                            memberForm.post(`${base}/members`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    memberForm.reset();
                                    memberOpen = false;
                                },
                            })
                        "
                    >
                        <FormErrors :errors="memberForm.errors" /><Field
                            label="Existing account"
                            ><select
                                v-model="memberForm.user_id"
                                required
                                class="control"
                            >
                                <option value="" disabled>Select a user</option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }} · {{ user.email }}
                                </option>
                            </select></Field
                        >
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium">
                                Roles
                            </legend>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <label
                                    v-for="role in roles.filter(
                                        (item) => item.is_active,
                                    )"
                                    :key="role.id"
                                    class="flex items-center gap-2 text-sm"
                                    ><input
                                        v-model="memberForm.role_ids"
                                        type="checkbox"
                                        :value="role.id"
                                    />{{ role.name }}</label
                                >
                            </div>
                        </fieldset>
                        <Button size="sm" :disabled="memberForm.processing"
                            >Add member</Button
                        >
                    </form>
                </FormSurface>
            </div>
        </section>
        <section id="roles" class="panel scroll-mt-5">
            <h2 class="section-title">Roles & permissions</h2>
            <p class="mb-4 text-sm text-muted-foreground">
                Roles are project-specific permission sets. Names do not
                determine access.
            </p>
            <div class="space-y-3">
                <RoleEditor
                    v-for="role in roles"
                    :key="role.id"
                    :base="base"
                    :role="role"
                    :grantable="grantablePermissions"
                    :editable="!!capabilities['access.manage']"
                /><RoleEditor
                    v-if="capabilities['access.manage']"
                    :base="base"
                    :grantable="grantablePermissions"
                    editable
                />
            </div>
            <p
                v-if="!roles.length && !capabilities['access.manage']"
                class="muted"
            >
                No roles configured.
            </p>
        </section>
    </PageFrame>
</template>
