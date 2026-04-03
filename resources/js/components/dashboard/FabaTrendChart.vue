<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { FabaChartData } from '@/types';

interface Props {
    data: FabaChartData[];
}

const props = defineProps<Props>();

const maxValue = computed(() => {
    const maxProduction = Math.max(...props.data.map((d) => d.production), 0);
    const maxUtilization = Math.max(...props.data.map((d) => d.utilization), 0);

    return Math.max(maxProduction, maxUtilization, 1);
});

const chartTicks = computed(() => {
    const steps = 4;

    return Array.from({ length: steps + 1 }, (_, index) => {
        const value = Math.round((maxValue.value / steps) * (steps - index));

        return {
            value,
            percent: (index / steps) * 100,
        };
    });
});

const productionBarColor = 'rgb(0 90 179)';
const utilizationBarColor = 'rgb(217 119 6)';

const totalProduction = computed(() =>
    props.data.reduce((sum, item) => sum + item.production, 0),
);

const totalUtilization = computed(() =>
    props.data.reduce((sum, item) => sum + item.utilization, 0),
);

const latestBalance = computed(() => props.data.at(-1)?.closing_balance ?? 0);

function barHeight(value: number): string {
    const chartBodyHeight = 224;

    return `${Math.max((value / maxValue.value) * chartBodyHeight, 8)}px`;
}
</script>

<template>
    <Card class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-blue-50/30 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-blue-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]">
        <CardHeader class="space-y-5 pb-4 sm:pb-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-blue-700/70">
                        FABA Insight
                    </p>
                    <CardTitle class="mt-2 text-lg tracking-tight sm:text-xl">
                        Produksi vs Pemanfaatan FABA
                    </CardTitle>
                    <CardDescription class="mt-2 max-w-xl text-sm text-slate-600 dark:text-slate-300">
                        6 bulan terakhir untuk membaca suplai, serapan, dan saldo akhir.
                    </CardDescription>
                </div>
                <div
                    class="rounded-[24px] border border-white/80 bg-white/85 px-4 py-3 text-right shadow-sm shadow-slate-200/50 backdrop-blur dark:border-slate-800/80 dark:bg-slate-900/85 dark:shadow-none"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Saldo akhir terbaru
                    </p>
                    <p class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ latestBalance.toLocaleString('id-ID') }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">ton</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="grid flex-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-[22px] border border-blue-200/70 bg-white/75 p-3 shadow-sm shadow-blue-100/30 dark:border-blue-900/70 dark:bg-slate-900/70 dark:shadow-none">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">
                            Produksi
                        </p>
                        <p class="mt-1 text-lg font-semibold text-blue-900 dark:text-blue-100">
                            {{ totalProduction.toLocaleString('id-ID') }} ton
                        </p>
                    </div>
                    <div class="rounded-[22px] border border-amber-200/70 bg-white/75 p-3 shadow-sm shadow-amber-100/30 dark:border-amber-900/70 dark:bg-slate-900/70 dark:shadow-none">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">
                            Pemanfaatan
                        </p>
                        <p class="mt-1 text-lg font-semibold text-amber-900 dark:text-amber-100">
                            {{ totalUtilization.toLocaleString('id-ID') }} ton
                        </p>
                    </div>
                    <div class="rounded-[22px] border border-slate-200/70 bg-white/75 p-3 shadow-sm shadow-slate-100/40 dark:border-slate-800/80 dark:bg-slate-900/70 dark:shadow-none">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            Periode
                        </p>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                            {{ props.data.length }} bulan
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-full border border-white/90 bg-white/90 px-4 py-2 text-xs shadow-sm shadow-slate-200/60 dark:border-slate-800/80 dark:bg-slate-900/85 dark:shadow-none">
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: productionBarColor }"
                        />
                        <span class="font-medium text-slate-600 dark:text-slate-300">Produksi</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: utilizationBarColor }"
                        />
                        <span class="font-medium text-slate-600 dark:text-slate-300">
                            Pemanfaatan
                        </span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-0">
            <div class="rounded-[28px] border border-white/90 bg-linear-to-b from-white via-white to-slate-50/80 p-5 shadow-inner shadow-slate-100/80 dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900 dark:shadow-none">
                <div class="grid grid-cols-[44px_minmax(0,1fr)] gap-4">
                    <div class="relative h-72">
                        <div
                            v-for="tick in chartTicks"
                            :key="tick.value"
                            class="absolute left-0 right-0 text-[11px] font-medium text-slate-400 dark:text-slate-500"
                            :style="{ top: `${tick.percent}%`, transform: 'translateY(-50%)' }"
                        >
                            {{ tick.value.toLocaleString('id-ID') }}
                        </div>
                    </div>

                    <div class="relative h-72">
                        <div
                            v-for="tick in chartTicks"
                            :key="`line-${tick.value}`"
                            class="absolute inset-x-0 border-t border-dashed border-slate-200 dark:border-slate-800"
                            :style="{ top: `${tick.percent}%` }"
                        />

                        <div class="flex h-full items-end justify-between gap-3">
                            <div
                                v-for="item in data"
                                :key="`${item.year}-${item.month}`"
                                class="flex min-w-0 flex-1 flex-col items-center gap-3"
                            >
                                <div class="flex h-56 w-full items-end justify-center gap-2 sm:gap-3">
                                    <div class="flex items-end gap-2">
                                        <div
                                            class="w-6 rounded-t-[14px] shadow-sm transition-all duration-500 sm:w-8"
                                            :style="{
                                                height: barHeight(item.production),
                                                backgroundColor: productionBarColor,
                                                boxShadow: '0 10px 24px -16px rgb(0 90 179 / 0.95)',
                                            }"
                                        ></div>
                                    </div>

                                    <div class="flex items-end gap-2">
                                        <div
                                            class="w-6 rounded-t-[14px] shadow-sm transition-all duration-500 sm:w-8"
                                            :style="{
                                                height: barHeight(item.utilization),
                                                backgroundColor: utilizationBarColor,
                                                boxShadow: '0 10px 24px -16px rgb(217 119 6 / 0.95)',
                                            }"
                                        ></div>
                                    </div>
                                </div>

                                <div class="rounded-[18px] border border-transparent px-2 py-2 text-center transition-all duration-200 hover:border-slate-200/80 hover:bg-white/70 dark:hover:border-slate-800 dark:hover:bg-slate-900/60">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-700 dark:text-slate-300">
                                        {{ item.label }}
                                    </p>
                                    <p class="mt-1 text-[11px] font-semibold text-blue-700">
                                        {{ item.production.toLocaleString('id-ID') }} produksi
                                    </p>
                                    <p class="mt-1 text-[11px] font-semibold text-amber-700">
                                        {{ item.utilization.toLocaleString('id-ID') }} manfaat
                                    </p>
                                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                        Saldo {{ item.closing_balance.toLocaleString('id-ID') }} ton
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
