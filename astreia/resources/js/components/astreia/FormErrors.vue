<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps<{ errors: object; reloadUrl?: string }>();
const entries = computed(() =>
    Object.entries(props.errors).filter(([, message]) => Boolean(message)),
);
</script>
<template>
    <div
        v-if="entries.length"
        role="alert"
        aria-live="polite"
        class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
    >
        <p class="font-medium">Please check the following</p>
        <ul class="mt-1 list-inside list-disc space-y-1">
            <li v-for="[key, message] in entries" :key="key">
                <span
                    v-if="!['_', 'lock_version', 'stage_id'].includes(key)"
                    class="font-medium"
                    >{{ key.replaceAll('_', ' ') }}: </span
                >{{ message }}
            </li>
        </ul>
        <Link
            v-if="
                reloadUrl &&
                entries.some(
                    ([key]) => key === 'lock_version' || key === 'stage_id',
                )
            "
            :href="reloadUrl"
            :preserve-state="false"
            class="mt-2 inline-block font-medium underline"
            >Reload the latest ticket</Link
        >
    </div>
</template>
