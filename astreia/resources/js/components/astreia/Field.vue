<script setup lang="ts">
defineProps<{ label: string; hint?: string }>();
</script>
<template>
    <div class="grid content-start gap-1.5 text-sm">
        <label class="grid gap-1.5">
            <span class="font-medium">{{ label }}</span>
            <slot />
        </label>
        <span v-if="hint" class="text-xs text-muted-foreground">{{
            hint
        }}</span>
    </div>
</template>
