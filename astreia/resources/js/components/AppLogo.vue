<script setup lang="ts">
import { Asterisk } from '@lucide/vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-foreground text-background shadow-xs"
    >
        <Asterisk class="size-6" :stroke-width="1.6" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="truncate text-base leading-tight font-semibold tracking-tight"
            >astreia<span class="text-primary">.</span></span
        >
        <span class="mt-0.5 text-[10px] text-muted-foreground"
            >Make work flow</span
        >
    </div>
</template>
