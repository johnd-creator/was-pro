<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

interface Props {
    title: string;
    value: string | number;
    unit?: string;
    href?: string;
    contextLabel?: string;
    hint?: string;
    color?: 'blue' | 'emerald' | 'orange' | 'red';
}

const props = withDefaults(defineProps<Props>(), {
    unit: '',
    color: 'blue',
});

const accentClasses = computed(() => {
    const colors = {
        blue: {
            shell: 'border-sky-200/70 bg-linear-to-br from-white via-sky-50/60 to-blue-100/65 dark:border-sky-900/70 dark:from-slate-950 dark:via-sky-950/35 dark:to-blue-950/35',
            glow: 'from-sky-300/25 via-blue-300/10 to-transparent dark:from-sky-500/10 dark:via-blue-500/5',
            badge: 'bg-sky-600 text-white shadow-sm shadow-sky-500/20',
            chip: 'border border-sky-200/70 bg-white/85 text-sky-700 dark:border-sky-800/80 dark:bg-slate-900/85 dark:text-sky-200',
            title: 'text-sky-900/80 dark:text-sky-100/85',
            value: 'text-slate-950 dark:text-slate-100',
            hint: 'text-sky-900/55 dark:text-sky-200/60',
        },
        emerald: {
            shell: 'border-emerald-200/70 bg-linear-to-br from-white via-emerald-50/70 to-teal-100/55 dark:border-emerald-900/70 dark:from-slate-950 dark:via-emerald-950/30 dark:to-teal-950/30',
            glow: 'from-emerald-300/20 via-teal-300/10 to-transparent dark:from-emerald-500/10 dark:via-teal-500/5',
            badge: 'bg-emerald-600 text-white shadow-sm shadow-emerald-500/20',
            chip: 'border border-emerald-200/70 bg-white/85 text-emerald-700 dark:border-emerald-800/80 dark:bg-slate-900/85 dark:text-emerald-200',
            title: 'text-emerald-950/80 dark:text-emerald-100/85',
            value: 'text-slate-950 dark:text-slate-100',
            hint: 'text-emerald-950/55 dark:text-emerald-200/60',
        },
        orange: {
            shell: 'border-orange-200/70 bg-linear-to-br from-white via-orange-50/75 to-amber-100/60 dark:border-orange-900/70 dark:from-slate-950 dark:via-orange-950/30 dark:to-amber-950/30',
            glow: 'from-orange-300/25 via-amber-300/10 to-transparent dark:from-orange-500/10 dark:via-amber-500/5',
            badge: 'bg-orange-600 text-white shadow-sm shadow-orange-500/20',
            chip: 'border border-orange-200/70 bg-white/85 text-orange-700 dark:border-orange-800/80 dark:bg-slate-900/85 dark:text-orange-200',
            title: 'text-orange-950/78 dark:text-orange-100/85',
            value: 'text-slate-950 dark:text-slate-100',
            hint: 'text-orange-950/55 dark:text-orange-200/60',
        },
        red: {
            shell: 'border-rose-200/75 bg-linear-to-br from-white via-rose-50/70 to-red-100/60 dark:border-rose-900/75 dark:from-slate-950 dark:via-rose-950/30 dark:to-red-950/30',
            glow: 'from-rose-300/25 via-red-300/10 to-transparent dark:from-rose-500/10 dark:via-red-500/5',
            badge: 'bg-rose-600 text-white shadow-sm shadow-rose-500/20',
            chip: 'border border-rose-200/70 bg-white/85 text-rose-700 dark:border-rose-800/80 dark:bg-slate-900/85 dark:text-rose-200',
            title: 'text-rose-950/80 dark:text-rose-100/85',
            value: 'text-slate-950 dark:text-slate-100',
            hint: 'text-rose-950/55 dark:text-rose-200/60',
        },
    };

    return colors[props.color];
});
</script>

<template>
    <Card
        :class="[
            'group relative overflow-hidden rounded-[26px] border shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_24px_50px_-28px_rgba(15,23,42,0.45)] dark:shadow-[0_18px_40px_-28px_rgba(2,6,23,0.9)]',
            accentClasses.shell,
        ]"
    >
        <div
            :class="[
                'pointer-events-none absolute inset-x-0 top-0 h-20 bg-linear-to-b blur-2xl',
                accentClasses.glow,
            ]"
        />
        <div class="absolute inset-y-5 left-0 w-1 rounded-r-full bg-white/65 dark:bg-slate-700/80" />
        <div class="absolute inset-x-5 top-0 h-px bg-white/80 dark:bg-slate-700/80" />
        <CardContent class="relative flex h-full flex-col p-5">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div
                    :class="[
                        'rounded-2xl p-3 transition-transform duration-300 group-hover:scale-105',
                        accentClasses.badge,
                    ]"
                >
                    <div class="h-2.5 w-2.5 rounded-full bg-current" />
                </div>
                <span
                    :class="[
                        'rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em]',
                        accentClasses.chip,
                    ]"
                >
                    {{ contextLabel || unit || 'Status' }}
                </span>
            </div>

            <p
                :class="[
                    'text-sm font-semibold tracking-tight',
                    accentClasses.title,
                ]"
            >
                {{ title }}
            </p>
            <p
                :class="[
                    'mt-2 text-3xl font-black tabular-nums tracking-tight',
                    accentClasses.value,
                ]"
            >
                {{ value }}
                <span v-if="unit" class="ml-1 text-sm font-medium text-slate-400 dark:text-slate-500">
                    {{ unit }}
                </span>
            </p>
            <p
                v-if="hint"
                :class="[
                    'mt-auto pt-2 line-clamp-2 text-[12px] leading-5',
                    accentClasses.hint,
                ]"
            >
                {{ hint }}
            </p>
        </CardContent>
    </Card>
</template>
