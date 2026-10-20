<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types/astreia';
defineProps<{ links: PaginationLink[] }>();
function label(value: string) {
    return value
        .replace(/&laquo;|&#171;/g, '«')
        .replace(/&raquo;|&#187;/g, '»')
        .replace(/&amp;/g, '&')
        .replace(/<[^>]*>/g, '');
}
</script>
<template>
    <nav
        v-if="links.length > 3"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-end gap-1 pt-3"
    >
        <template v-for="(link, index) in links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                :class="link.active ? 'bg-primary text-primary-foreground' : ''"
                :aria-current="link.active ? 'page' : undefined"
                >{{ label(link.label) }}</Link
            >
            <span v-else class="px-3 py-1.5 text-sm text-muted-foreground/60">{{
                label(link.label)
            }}</span>
        </template>
    </nav>
</template>
