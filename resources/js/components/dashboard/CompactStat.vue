<script setup lang="ts">
import {
    Activity,
    CheckCircle2,
    CircleAlert,
    ClipboardList,
    Factory,
    PackageCheck,
    Scale,
    ShieldAlert,
} from 'lucide-vue-next';
import { computed } from 'vue';

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
            shell: 'border-sky-200/70 bg-linear-to-br from-white via-sky-50/70 to-blue-100/60 dark:border-sky-800/50 dark:from-slate-900/40 dark:via-sky-950/50 dark:to-blue-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-sky-500/5 dark:before:to-blue-500/5 dark:before:content-[""]',
            title: 'text-slate-800 dark:text-slate-100',
            value: 'text-slate-950 dark:text-white',
            unit: 'text-slate-400 dark:text-slate-300',
            context: 'border-slate-200/85 bg-white/90 text-slate-700 dark:border-sky-700/60 dark:bg-sky-950/50 dark:text-sky-100',
            hint: 'text-slate-600 dark:text-slate-300',
            icon: 'text-sky-700/75 dark:text-sky-300/60',
        },
        emerald: {
            shell: 'border-emerald-200/70 bg-linear-to-br from-white via-emerald-50/70 to-teal-100/55 dark:border-emerald-800/50 dark:from-slate-900/40 dark:via-emerald-950/50 dark:to-teal-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-emerald-500/5 dark:before:to-teal-500/5 dark:before:content-[""]',
            title: 'text-slate-800 dark:text-slate-100',
            value: 'text-slate-950 dark:text-white',
            unit: 'text-slate-400 dark:text-slate-300',
            context: 'border-slate-200/85 bg-white/90 text-slate-700 dark:border-emerald-700/60 dark:bg-emerald-950/50 dark:text-emerald-100',
            hint: 'text-slate-600 dark:text-slate-300',
            icon: 'text-emerald-700/75 dark:text-emerald-300/60',
        },
        orange: {
            shell: 'border-orange-200/70 bg-linear-to-br from-white via-orange-50/75 to-amber-100/60 dark:border-orange-800/50 dark:from-slate-900/40 dark:via-orange-950/50 dark:to-amber-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-orange-500/5 dark:before:to-amber-500/5 dark:before:content-[""]',
            title: 'text-slate-800 dark:text-slate-100',
            value: 'text-slate-950 dark:text-white',
            unit: 'text-slate-400 dark:text-slate-300',
            context: 'border-slate-200/85 bg-white/90 text-slate-700 dark:border-orange-700/60 dark:bg-orange-950/50 dark:text-orange-100',
            hint: 'text-slate-600 dark:text-slate-300',
            icon: 'text-orange-700/75 dark:text-orange-300/60',
        },
        red: {
            shell: 'border-rose-200/70 bg-linear-to-br from-white via-rose-50/70 to-red-100/60 dark:border-rose-800/50 dark:from-slate-900/40 dark:via-rose-950/50 dark:to-red-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-rose-500/5 dark:before:to-red-500/5 dark:before:content-[""]',
            title: 'text-slate-800 dark:text-slate-100',
            value: 'text-slate-950 dark:text-white',
            unit: 'text-slate-400 dark:text-slate-300',
            context: 'border-slate-200/85 bg-white/90 text-slate-700 dark:border-rose-700/60 dark:bg-rose-950/50 dark:text-rose-100',
            hint: 'text-slate-600 dark:text-slate-300',
            icon: 'text-rose-700/75 dark:text-rose-300/60',
        },
    };

    return colors[props.color];
});

const icon = computed(() => {
    const map: Record<string, typeof Activity> = {
        'Total Limbah': Activity,
        'Total Catatan Limbah': Activity,
        'Limbah Terangkut': PackageCheck,
        'Belum Terangkut': CircleAlert,
        'Menunggu Ditinjau': ClipboardList,
        'Melewati Batas Simpan': ShieldAlert,
        'FABA Produksi': Factory,
        'FABA Pemanfaatan': CheckCircle2,
        'Saldo FABA': Scale,
    };

    return map[props.title] ?? Activity;
});
</script>

<template>
    <div
        :class="[
            'group relative overflow-hidden rounded-[26px] border p-4 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_24px_50px_-28px_rgba(15,23,42,0.45)]',
            accentClasses.shell,
        ]"
    >
        <div class="pointer-events-none absolute right-3 bottom-3">
            <component :is="icon" class="size-11" :class="accentClasses.icon" />
        </div>

        <div class="relative flex h-full min-h-[126px] flex-col gap-3 pr-14">
            <div class="mb-1 flex items-start justify-between gap-3 pr-12">
                <span
                    v-if="contextLabel"
                    :class="[
                        'rounded-full border px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] uppercase',
                        accentClasses.context,
                    ]"
                >
                    {{ contextLabel }}
                </span>
            </div>
            <p
                :class="[
                    'pr-12 text-sm font-semibold tracking-tight',
                    accentClasses.title,
                ]"
            >
                {{ title }}
            </p>
            <p
                :class="[
                    'pr-12 text-3xl font-black tracking-tight tabular-nums',
                    accentClasses.value,
                ]"
            >
                {{ value }}
                <span
                    v-if="unit"
                    :class="[
                        'ml-1 text-sm font-medium',
                        accentClasses.unit,
                    ]"
                >
                    {{ unit }}
                </span>
            </p>

            <div
                v-if="hint"
                class="mt-auto text-[12px] leading-5"
                :class="accentClasses.hint"
            >
                {{ hint }}
            </div>
        </div>
    </div>
</template>
