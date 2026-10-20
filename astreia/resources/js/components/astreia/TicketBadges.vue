<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
defineProps<{
    priority?: string;
    is_intake_stub?: boolean;
    is_blocked?: boolean;
    is_cancelled?: boolean;
}>();
</script>
<template>
    <span class="inline-flex flex-wrap gap-1">
        <Badge
            v-if="priority"
            :variant="priority === 'urgent' ? 'destructive' : 'secondary'"
            class="capitalize"
            >{{ priority }}</Badge
        >
        <Badge
            v-if="is_intake_stub"
            variant="outline"
            class="border-amber-500/50 text-amber-700 dark:text-amber-400"
            >Incomplete intake</Badge
        >
        <Badge
            v-if="is_blocked"
            variant="outline"
            class="border-amber-500/50 text-amber-700 dark:text-amber-400"
            >Blocked</Badge
        >
        <Badge v-if="is_cancelled" variant="outline">Cancelled</Badge>
    </span>
</template>
