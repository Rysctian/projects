<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
const page = usePage();
const section = computed(() =>
    page.url.startsWith('/admin')
        ? 'Administration'
        : page.url.startsWith('/settings')
          ? 'Account settings'
          : 'Projects',
);
const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 bg-card/70 px-4 sm:px-7"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            <div v-else class="flex items-center gap-2 text-xs">
                <Link
                    href="/projects"
                    class="text-muted-foreground hover:text-foreground"
                    >Workspace</Link
                >
                <ChevronRight class="size-3 text-muted-foreground/60" />
                <span class="font-medium">{{ section }}</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span
                class="hidden items-center gap-1.5 text-[11px] text-muted-foreground sm:flex"
                >A little clarity. A lot of progress.</span
            >
            <Button
                size="icon"
                variant="ghost"
                :aria-label="
                    resolvedAppearance === 'dark'
                        ? 'Switch to light theme'
                        : 'Switch to dark theme'
                "
                @click="
                    updateAppearance(
                        resolvedAppearance === 'dark' ? 'light' : 'dark',
                    )
                "
            >
                <Sun v-if="resolvedAppearance === 'dark'" class="size-4" /><Moon
                    v-else
                    class="size-4"
                />
            </Button>
        </div>
    </header>
</template>
