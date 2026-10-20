<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
        class="astreia mx-auto w-full max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8"
    >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-1.5">
                <Link
                    v-if="back"
                    :href="back"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    >← {{ backLabel ?? 'Back to project' }}</Link
                >
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                >
                    {{ eyebrow }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="max-w-3xl text-sm text-muted-foreground"
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
