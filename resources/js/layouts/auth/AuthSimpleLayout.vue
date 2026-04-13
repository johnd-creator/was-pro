<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Building2 } from 'lucide-vue-next';
import AppLogoAuth from '@/components/AppLogoAuth.vue';
import { home } from '@/routes';

const page = usePage<{ name?: string }>();
const appName = page.props.name ?? 'WASPRO';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative min-h-svh overflow-hidden bg-[linear-gradient(180deg,#f8fafc_0%,#ffffff_48%,#f5f9ff_100%)] dark:bg-[linear-gradient(180deg,#020617_0%,#0f172a_48%,#020617_100%)]"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[360px] bg-linear-to-b from-cyan-50 via-white/50 to-transparent dark:from-cyan-950/20 dark:via-slate-950/20"
        />
        <div
            class="pointer-events-none absolute inset-y-0 left-0 w-full bg-[radial-gradient(circle_at_top_left,rgba(34,211,238,0.11),transparent_30%),radial-gradient(circle_at_right,rgba(59,130,246,0.08),transparent_26%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(34,211,238,0.12),transparent_26%),radial-gradient(circle_at_right,rgba(14,165,233,0.08),transparent_22%)]"
        />
        <div
            class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(148,163,184,0.06)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.06)_1px,transparent_1px)] [mask-image:radial-gradient(circle_at_center,black,transparent_82%)] bg-[size:96px_96px] opacity-25 dark:opacity-8"
        />
        <div
            class="pointer-events-none absolute -top-20 left-[8%] h-72 w-72 rounded-full bg-cyan-200/20 blur-3xl dark:bg-cyan-500/10"
        />
        <div
            class="pointer-events-none absolute top-32 right-[10%] h-80 w-80 rounded-full bg-blue-200/16 blur-3xl dark:bg-blue-500/10"
        />

        <div
            class="relative mx-auto flex min-h-svh w-full max-w-7xl items-center px-6 py-8 md:px-10 lg:px-12"
        >
            <div
                class="grid w-full gap-8 lg:grid-cols-[minmax(0,0.95fr)_minmax(420px,460px)] lg:items-center lg:gap-20"
            >
                <section
                    class="hidden lg:flex lg:flex-col lg:gap-12 lg:text-slate-950 dark:lg:text-slate-100"
                >
                    <Link
                        :href="home()"
                        class="inline-flex w-fit items-center rounded-full border border-slate-200/80 bg-white/88 px-4 py-2 shadow-[0_20px_45px_-32px_rgba(15,23,42,0.22)] backdrop-blur transition-colors hover:border-slate-300 dark:border-slate-800/80 dark:bg-slate-950/82 dark:hover:border-slate-700"
                    >
                        <AppLogoAuth />
                    </Link>

                    <div class="max-w-lg space-y-7">
                        <div
                            class="inline-flex w-fit items-center gap-2 rounded-full border border-cyan-200/80 bg-cyan-50/90 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] text-cyan-900 uppercase shadow-sm dark:border-cyan-900/70 dark:bg-cyan-950/40 dark:text-cyan-100"
                        >
                            <Building2 class="size-3.5" />
                            Waste Operations Platform
                        </div>

                        <div class="space-y-5">
                            <h2
                                class="max-w-xl text-5xl font-semibold tracking-[-0.04em] text-balance"
                            >
                                Kelola operasional limbah dan FABA dalam satu
                                sistem.
                            </h2>
                            <p
                                class="max-w-md text-[15px] leading-7 text-slate-600 dark:text-slate-300"
                            >
                                Masuk ke {{ appName }} untuk memantau catatan
                                limbah, transportasi, rekap, dan proses
                                approval.
                            </p>
                        </div>

                        <div
                            class="inline-flex w-fit items-center gap-2 text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            <span>Compliance and operations</span>
                            <ArrowUpRight class="size-4" />
                        </div>
                    </div>
                </section>

                <section class="w-full">
                    <div
                        class="overflow-hidden rounded-[32px] border border-slate-200/85 bg-white/95 shadow-[0_34px_90px_-42px_rgba(15,23,42,0.42)] backdrop-blur-xl dark:border-slate-800/85 dark:bg-slate-950/90 dark:shadow-[0_34px_90px_-42px_rgba(2,6,23,0.92)]"
                    >
                        <div
                            class="border-b border-slate-200/80 px-6 py-5 sm:px-8 dark:border-slate-800/80"
                        >
                            <div class="flex items-center gap-3">
                                <Link
                                    :href="home()"
                                    class="inline-flex rounded-full border border-slate-200/80 bg-white/85 px-3 py-1.5 shadow-sm lg:hidden dark:border-slate-800/80 dark:bg-slate-950/80"
                                >
                                    <AppLogoAuth />
                                </Link>
                                <div
                                    class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200/80 bg-slate-50 shadow-sm lg:flex dark:border-slate-800/80 dark:bg-slate-900"
                                >
                                    <Building2
                                        class="size-5 text-slate-700 dark:text-slate-200"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-cyan-700/80 uppercase dark:text-cyan-300/80"
                                    >
                                        Account Access
                                    </p>
                                    <p
                                        class="truncate text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        Sistem pengelolaan limbah dan FABA
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-8 sm:px-8 sm:py-10">
                            <div class="flex flex-col gap-8">
                                <div class="space-y-3">
                                    <h1
                                        v-if="title"
                                        class="text-3xl font-semibold tracking-[-0.03em] text-slate-950 dark:text-slate-100"
                                    >
                                        {{ title }}
                                    </h1>
                                    <p
                                        v-if="description"
                                        class="max-w-md text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ description }}
                                    </p>
                                </div>

                                <slot />
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
