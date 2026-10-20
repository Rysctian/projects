<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { FolderKanban, Plus, ArrowUpRight } from '@lucide/vue';
import { ref } from 'vue';
import PageFrame from '@/components/astreia/PageFrame.vue';
import Field from '@/components/astreia/Field.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import Pagination from '@/components/astreia/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import type { Paginated, Project } from '@/types/astreia';
defineProps<{
    projects: Paginated<Project & { open_tickets_count: number }>;
    canCreate: boolean;
}>();
const creating = ref(false);
const form = useForm({ code: '', name: '', description: '' });
function create() {
    form.post('/projects', {
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}
</script>
<template>
    <PageFrame
        title="Projects"
        eyebrow="Workspace"
        description="Your systems, teams, and the work moving them forward."
    >
        <template #actions
            ><Button v-if="canCreate" @click="creating = !creating"
                ><Plus class="size-4" />New project</Button
            ></template
        >
        <form
            v-if="creating && canCreate"
            class="panel space-y-4"
            @submit.prevent="create"
        >
            <h2 class="section-title">Create a project</h2>
            <FormErrors :errors="form.errors" />
            <div class="grid gap-4 sm:grid-cols-3">
                <Field label="Code" hint="Permanent prefix for ticket keys."
                    ><Input
                        v-model="form.code"
                        required
                        placeholder="HRIS" /></Field
                ><Field label="Name" class="sm:col-span-2"
                    ><Input
                        v-model="form.name"
                        required
                        placeholder="Human Resources"
                /></Field>
            </div>
            <Field label="Description">
                <textarea
                    v-model="form.description"
                    class="control"
                    placeholder="What does this project support?"
                />
            </Field>
            <div class="flex gap-2">
                <Button :disabled="form.processing">{{
                    form.processing ? 'Creating…' : 'Create project'
                }}</Button
                ><Button type="button" variant="ghost" @click="creating = false"
                    >Cancel</Button
                >
            </div>
        </form>
        <div
            v-if="projects.data.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Link
                v-for="project in projects.data"
                :key="project.id"
                :href="`/projects/${project.id}`"
                class="panel group flex flex-col gap-5 transition hover:border-foreground/25 hover:shadow-sm"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-lg bg-muted"
                        ><FolderKanban class="size-5" /></span
                    ><Badge variant="outline" class="capitalize">{{
                        project.status
                    }}</Badge>
                </div>
                <div class="flex-1">
                    <p class="mb-1 font-mono text-xs text-muted-foreground">
                        {{ project.code }}
                    </p>
                    <h2 class="font-semibold">{{ project.name }}</h2>
                    <p class="mt-2 line-clamp-2 text-sm text-muted-foreground">
                        {{ project.description || 'No description yet.' }}
                    </p>
                </div>
                <div
                    class="flex items-center justify-between border-t pt-4 text-xs text-muted-foreground"
                >
                    <span
                        ><strong class="text-foreground">{{
                            project.open_tickets_count
                        }}</strong>
                        open tickets</span
                    ><ArrowUpRight
                        class="size-4 transition group-hover:translate-x-0.5"
                    />
                </div>
            </Link>
        </div>
        <div v-else class="panel empty">
            <FolderKanban class="mx-auto mb-3 size-8" />
            <p class="font-medium text-foreground">No projects yet</p>
            <p class="mt-1">
                {{
                    canCreate
                        ? 'Create your first project to start tracking work.'
                        : 'Projects will appear here when you are granted access.'
                }}
            </p>
        </div>
        <Pagination :links="projects.links" />
    </PageFrame>
</template>
