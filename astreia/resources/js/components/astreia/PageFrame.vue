<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
defineProps<{
    title: string;
    eyebrow?: string;
    description?: string;
    back?: string;
    backLabel?: string;
}>();
</script>

<template>
    <Head :title="title" />
    <main
        class="astreia mx-auto w-full max-w-[1800px] space-y-7 p-4 sm:p-7 lg:p-10"
    >
        <header class="flex flex-wrap items-center justify-between gap-5">
            <div class="min-w-0 space-y-1.5">
                <Link
                    v-if="back"
                    :href="back"
                    class="mb-4 inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    ><ArrowLeft class="size-3.5" />
                    {{ backLabel ?? 'Back to project' }}</Link
                >
                <p
                    v-if="eyebrow"
                    class="mb-2 text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    {{ eyebrow }}
                </p>
                <h1
                    class="text-2xl font-semibold tracking-[-0.035em] sm:text-3xl"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="max-w-2xl text-sm leading-relaxed text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <slot />
    </main>
</template>

<style>
.astreia .panel {
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    background: var(--card);
    padding: 1.25rem;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.025);
}
.astreia .section-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 1rem;
}
.astreia .control {
    width: 100%;
    min-height: 2.25rem;
    border: 1px solid var(--input);
    border-radius: 0.375rem;
    background: transparent;
    padding: 0.4rem 0.65rem;
    font-size: 0.875rem;
}
.astreia .control:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.astreia select.control option {
    background: var(--background);
    color: var(--foreground);
}
.astreia .control:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.astreia textarea.control {
    min-height: 6rem;
    resize: vertical;
}
.astreia .muted {
    font-size: 0.8rem;
    color: var(--muted-foreground);
}
.astreia .empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--muted-foreground);
    font-size: 0.875rem;
}
.astreia .data-table {
    width: 100%;
    text-align: left;
    font-size: 0.875rem;
}
.astreia .data-table th {
    padding: 0.7rem 1rem;
    font-size: 0.75rem;
    color: var(--muted-foreground);
    font-weight: 500;
    background: var(--muted);
    white-space: nowrap;
}
.astreia .data-table td {
    padding: 0.85rem 1rem;
    border-top: 1px solid var(--border);
    vertical-align: top;
}
.astreia input[type='checkbox'],
.astreia input[type='radio'] {
    accent-color: var(--primary);
}
.astreia summary {
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
}
.astreia summary:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 4px;
}
</style>
