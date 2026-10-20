<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Asterisk,
    ArrowUpRight,
    CircleCheck,
    CircleDashed,
    CircleDot,
} from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-2">
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-[#10131b] p-12 text-white lg:flex xl:p-16"
        >
            <Link
                :href="home()"
                class="flex w-fit items-center gap-2.5 text-xl font-semibold tracking-tight"
                aria-label="Astreia home"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-xl border border-white/15 bg-white/5"
                    ><Asterisk class="size-6" /></span
                >astreia<span class="-ml-2 text-blue-400">.</span>
            </Link>
            <div class="my-16 max-w-lg">
                <p
                    class="mb-5 text-[10px] font-medium tracking-[0.2em] text-white/45 uppercase"
                >
                    A workspace for what comes next
                </p>
                <h2
                    class="text-4xl leading-[1.12] font-medium tracking-[-0.045em] xl:text-5xl"
                >
                    A little clarity.<br />A lot of progress.
                </h2>
                <p class="mt-6 max-w-sm text-sm leading-7 text-white/50">
                    Bring your projects, people, and workflows together. Give
                    every idea a clear path forward.
                </p>
                <div class="mt-12 grid grid-cols-3 gap-3" aria-hidden="true">
                    <div
                        v-for="(item, index) in ['Plan', 'Build', 'Deliver']"
                        :key="item"
                        class="rounded-xl border border-white/10 bg-white/[0.025] p-3.5"
                    >
                        <p
                            class="flex items-center gap-1.5 text-[11px] text-white/60"
                        >
                            <component
                                :is="
                                    index === 0
                                        ? CircleDashed
                                        : index === 1
                                          ? CircleDot
                                          : CircleCheck
                                "
                                :class="[
                                    'size-3',
                                    index === 2
                                        ? 'text-emerald-400'
                                        : 'text-blue-400',
                                ]"
                            />{{ item }}
                        </p>
                        <div
                            class="mt-4 rounded-md border border-white/8 bg-white/5 p-3"
                        >
                            <div class="h-1 w-8 rounded-full bg-white/10" />
                            <div
                                class="mt-3 h-1 w-full rounded-full bg-white/20"
                            />
                            <div
                                class="mt-1.5 h-1 w-2/3 rounded-full bg-white/10"
                            />
                            <ArrowUpRight
                                class="mt-3 ml-auto size-3 text-white/30"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-[11px] tracking-wide text-white/35">
                Thoughtful tools. Meaningful work.
            </p>
        </aside>
        <main class="flex flex-col items-center justify-center p-5 sm:p-10">
            <Link
                :href="home()"
                class="mb-8 flex items-center gap-2 text-xl font-semibold tracking-tight lg:hidden"
                aria-label="Astreia home"
                ><Asterisk class="size-7" />astreia.</Link
            >
            <Card class="w-full max-w-md border-border/60 py-0 shadow-xs">
                <CardContent class="p-7 sm:p-9">
                    <div class="mb-8 space-y-2">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </CardContent>
            </Card>
            <p class="mt-6 text-[11px] text-muted-foreground">
                Your next chapter starts here.
            </p>
        </main>
    </div>
</template>
