<script setup lang="ts">
import {
    Factory,
    PackageCheck,
    Scale,
    ShieldAlert,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';

interface FabaHeroStats {
    total_production: number;
    total_utilization: number;
    current_balance: number;
    negative_periods: number;
}

interface StatCard {
    title: string;
    value: number;
    tone: 'blue' | 'emerald' | 'orange' | 'red';
    icon: LucideIcon;
    hint: string;
    unit: string;
}

const props = withDefaults(defineProps<{
    year: number;
    stats: FabaHeroStats;
    variant?: 'full' | 'summary';
}>(), {
    variant: 'full',
});

const statCards = computed<StatCard[]>(() => {
    const cards: StatCard[] = [
        {
            title: 'Total Produksi',
            value: props.stats.total_production,
            tone: 'blue',
            icon: Factory,
            hint: `Produksi sepanjang ${props.year}`,
            unit: 'ton',
        },
        {
            title: 'Total Pemanfaatan',
            value: props.stats.total_utilization,
            tone: 'emerald',
            icon: PackageCheck,
            hint: 'Serapan material terverifikasi',
            unit: 'ton',
        },
        {
            title: 'Saldo TPS',
            value: props.stats.current_balance,
            tone: props.stats.current_balance < 0 ? 'red' : 'blue',
            icon: Scale,
            hint: 'Posisi stok terbaru',
            unit: 'ton',
        },
        {
            title: 'Periode Negatif',
            value: props.stats.negative_periods,
            tone: props.stats.negative_periods > 0 ? 'orange' : 'emerald',
            icon: ShieldAlert,
            hint: props.stats.negative_periods > 0 ? 'Perlu rekonsiliasi' : 'Tidak ada anomali',
            unit: 'periode',
        },
    ];

    return props.variant === 'summary' ? cards.slice(0, 3) : cards;
});

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID');
}

function toneClasses(tone: StatCard['tone']): string {
    const map = {
        blue: 'border-sky-200/70 bg-linear-to-br from-white via-sky-50/70 to-blue-100/60 dark:border-sky-800/50 dark:from-slate-900/40 dark:via-sky-950/50 dark:to-blue-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-sky-500/5 dark:before:to-blue-500/5 dark:before:content-[""]',
        emerald: 'border-emerald-200/70 bg-linear-to-br from-white via-emerald-50/70 to-teal-100/55 dark:border-emerald-800/50 dark:from-slate-900/40 dark:via-emerald-950/50 dark:to-teal-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-emerald-500/5 dark:before:to-teal-500/5 dark:before:content-[""]',
        orange: 'border-orange-200/70 bg-linear-to-br from-white via-orange-50/75 to-amber-100/60 dark:border-orange-800/50 dark:from-slate-900/40 dark:via-orange-950/50 dark:to-amber-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-orange-500/5 dark:before:to-amber-500/5 dark:before:content-[""]',
        red: 'border-rose-200/70 bg-linear-to-br from-white via-rose-50/70 to-red-100/60 dark:border-rose-800/50 dark:from-slate-900/40 dark:via-rose-950/50 dark:to-red-950/40 dark:before:absolute dark:before:inset-0 dark:before:bg-gradient-to-br dark:before:from-rose-500/5 dark:before:to-red-500/5 dark:before:content-[""]',
    };

    return map[tone];
}

function iconClasses(tone: StatCard['tone']): string {
    const map = {
        blue: 'text-sky-700/75 dark:text-sky-300/60',
        emerald: 'text-emerald-700/75 dark:text-emerald-300/60',
        orange: 'text-orange-700/75 dark:text-orange-300/60',
        red: 'text-rose-700/75 dark:text-rose-300/60',
    };

    return map[tone];
}
</script>

<template>
    <div
        v-for="card in statCards"
        :key="card.title"
        :class="[
            'group relative overflow-hidden rounded-[26px] border p-4 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_24px_50px_-28px_rgba(15,23,42,0.45)]',
            toneClasses(card.tone),
        ]"
    >
        <div class="pointer-events-none absolute right-3 bottom-3">
            <component :is="card.icon" class="size-11" :class="iconClasses(card.tone)" />
        </div>

        <div class="mb-4 flex items-start justify-between gap-3 pr-12">
            <span
                class="rounded-full border border-slate-200/85 bg-white/90 px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] text-slate-700 uppercase dark:border-slate-700/60 dark:bg-slate-900/60 dark:text-slate-100"
            >
                {{ year }}
            </span>
        </div>
        <p class="pr-12 text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100">
            {{ card.title }}
        </p>
        <p class="mt-2 pr-12 text-3xl font-black tracking-tight text-slate-950 dark:text-white">
            {{ formatNumber(card.value) }}
            <span class="ml-1 text-sm font-medium text-slate-400 dark:text-slate-300">{{ card.unit }}</span>
        </p>
        <p class="mt-2 pr-14 text-[12px] leading-5 text-slate-600 dark:text-slate-300">
            {{ card.hint }}
        </p>
    </div>
</template>
