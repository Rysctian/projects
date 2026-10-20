<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Flag, Ban, CircleDashed, CircleAlert } from '@lucide/vue';
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
            :class="[
                'rounded-md px-1.5 py-0.5 text-[10px] font-medium capitalize',
                priority === 'high'
                    ? 'bg-orange-500/10 text-orange-700 dark:text-orange-400'
                    : priority === 'low' || priority === 'normal'
                      ? 'bg-muted text-muted-foreground'
                      : '',
            ]"
            ><Flag class="!size-2.5" />{{ priority }}</Badge
        >
        <Badge
            v-if="is_intake_stub"
            variant="outline"
            class="border-amber-500/50 text-amber-700 dark:text-amber-400"
            ><CircleDashed class="size-3" />Incomplete intake</Badge
        >
        <Badge
            v-if="is_blocked"
            variant="outline"
            class="border-amber-500/50 text-amber-700 dark:text-amber-400"
            ><CircleAlert class="size-3" />Blocked</Badge
        >
        <Badge v-if="is_cancelled" variant="outline"
            ><Ban class="size-3" />Cancelled</Badge
        >
    </span>
</template>
