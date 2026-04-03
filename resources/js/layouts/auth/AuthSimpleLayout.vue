<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-[linear-gradient(180deg,#f8fafc_0%,#ffffff_48%,#f8fafc_100%)] p-6 dark:bg-[linear-gradient(180deg,#020617_0%,#0f172a_48%,#020617_100%)] md:p-10"
    >
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[320px] bg-linear-to-b from-cyan-50 via-transparent to-transparent dark:from-cyan-950/30" />
        <div class="pointer-events-none absolute -top-16 left-[12%] h-64 w-64 rounded-full bg-cyan-200/25 blur-3xl dark:bg-cyan-500/12" />
        <div class="pointer-events-none absolute top-24 right-[8%] h-72 w-72 rounded-full bg-amber-200/20 blur-3xl dark:bg-amber-500/10" />

        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="overflow-hidden rounded-[30px] border border-slate-200/80 bg-white/92 p-6 shadow-[0_28px_70px_-42px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800/80 dark:bg-slate-950/88 dark:shadow-[0_28px_70px_-42px_rgba(2,6,23,0.9)]">
                    <div class="flex flex-col gap-8">
                        <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200/80 bg-slate-50 shadow-sm dark:border-slate-800/80 dark:bg-slate-900"
                        >
                            <AppLogoIcon
                                class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-cyan-700/70 dark:text-cyan-300/70">
                            Account Access
                        </p>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ title }}</h1>
                        <p class="text-center text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
