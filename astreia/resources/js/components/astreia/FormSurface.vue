<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        trigger?: string;
        mode?: 'dialog' | 'drawer';
        busy?: boolean;
    }>(),
    { mode: 'dialog', busy: false },
);
const open = defineModel<boolean>('open', { default: false });
const desktop = useMediaQuery('(min-width: 640px)');
let previousFocus: HTMLElement | null = null;
watch(open, (value) => {
    if (value && typeof document !== 'undefined')
        previousFocus = document.activeElement as HTMLElement | null;
});
function restoreFocus(event: Event) {
    if (previousFocus?.isConnected) {
        event.preventDefault();
        previousFocus.focus();
    }
}
function preventWhileBusy(event: Event, busy: boolean) {
    if (busy) event.preventDefault();
}
</script>

<template>
    <Dialog v-if="mode === 'dialog' && desktop" v-model:open="open">
        <DialogTrigger v-if="$slots.trigger || trigger" as-child>
            <slot name="trigger"
                ><Button type="button" variant="outline">{{
                    trigger
                }}</Button></slot
            >
        </DialogTrigger>
        <DialogContent
            class="astreia form-surface max-h-[90dvh] overflow-y-auto bg-card sm:max-w-xl"
            @close-auto-focus="restoreFocus"
            @interact-outside="preventWhileBusy($event, busy)"
            @escape-key-down="preventWhileBusy($event, busy)"
        >
            <DialogHeader class="border-b pr-6 pb-5">
                <DialogTitle class="text-xl tracking-tight">{{
                    title
                }}</DialogTitle>
                <DialogDescription class="mt-1.5 leading-relaxed">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <slot />
        </DialogContent>
    </Dialog>
    <Sheet v-else v-model:open="open">
        <SheetTrigger v-if="$slots.trigger || trigger" as-child>
            <slot name="trigger"
                ><Button type="button" variant="outline">{{
                    trigger
                }}</Button></slot
            >
        </SheetTrigger>
        <SheetContent
            :side="desktop ? 'right' : 'bottom'"
            :class="[
                'astreia form-surface overflow-y-auto bg-card',
                desktop ? 'w-full sm:max-w-2xl' : 'max-h-[92dvh] rounded-t-2xl',
            ]"
            @close-auto-focus="restoreFocus"
            @interact-outside="preventWhileBusy($event, busy)"
            @escape-key-down="preventWhileBusy($event, busy)"
        >
            <div
                v-if="!desktop"
                class="mx-auto mt-3 h-1 w-9 shrink-0 rounded-full bg-muted-foreground/25"
                aria-hidden="true"
            />
            <SheetHeader class="border-b p-6 pr-12 text-left">
                <SheetTitle class="text-xl tracking-tight">{{
                    title
                }}</SheetTitle>
                <SheetDescription class="mt-1.5 leading-relaxed">{{
                    description
                }}</SheetDescription>
            </SheetHeader>
            <div class="px-6 pb-6"><slot /></div>
        </SheetContent>
    </Sheet>
</template>
