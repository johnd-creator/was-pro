<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { FabaChartData } from '@/types';

interface Props {
    data: FabaChartData[];
}

const props = defineProps<Props>();

const productionBarColor = 'rgb(37 99 235)';
const utilizationBarColor = 'rgb(217 119 6)';

const totalProduction = computed(() =>
    props.data.reduce((sum, item) => sum + item.production, 0),
);

const totalUtilization = computed(() =>
    props.data.reduce((sum, item) => sum + item.utilization, 0),
);

const latestBalance = computed(() => props.data.at(-1)?.closing_balance ?? 0);

const chartScale = computed(() => {
    const rawMax = Math.max(
        ...props.data.map((item) =>
            Math.max(item.production, item.utilization),
        ),
        0,
    );

    if (rawMax <= 20) {
        return {
            max: 20,
            step: 5,
        };
    }

    const roughStep = rawMax / 4;
    const magnitude = 10 ** Math.floor(Math.log10(roughStep));
    const normalized = roughStep / magnitude;

    const niceFactor =
        normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
    const step = niceFactor * magnitude;

    return {
        max: step * 4,
        step,
    };
});

const chartTicks = computed(() =>
    Array.from({ length: 5 }, (_, index) => ({
        value: chartScale.value.max - chartScale.value.step * index,
        percent: (index / 4) * 100,
    })),
);

function barHeight(value: number): string {
    if (value <= 0) {
        return '0%';
    }

    return `${Math.max((value / chartScale.value.max) * 100, 6)}%`;
}

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID');
}
</script>

<template>
    <Card
        class="wm-panel-elevated overflow-hidden rounded-2xl border shadow-sm"
    >
        <CardHeader
            class="wm-border-default border-b bg-slate-50/40 pb-4 dark:bg-slate-900/30"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="space-y-1.5">
                    <CardTitle class="wm-text-primary text-base">
                        Produksi vs Pemanfaatan FABA
                    </CardTitle>
                    <CardDescription
                        class="wm-text-secondary text-sm leading-6"
                    >
                        Ringkasan tonase per bulan untuk melihat ritme produksi,
                        serapan, dan posisi saldo aktif dengan lebih jelas.
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: productionBarColor }"
                        />
                        <span class="wm-text-secondary font-medium">
                            Produksi
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: utilizationBarColor }"
                        />
                        <span class="wm-text-secondary font-medium">
                            Pemanfaatan
                        </span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-5 pt-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="wm-surface-subtle rounded-2xl border px-4 py-3">
                    <p
                        class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase"
                    >
                        Total Produksi
                    </p>
                    <p
                        class="wm-text-primary mt-2 text-2xl font-semibold tracking-tight"
                    >
                        {{ formatNumber(totalProduction) }}
                        <span class="wm-text-muted text-sm font-medium"
                            >ton</span
                        >
                    </p>
                </div>
                <div class="wm-surface-subtle rounded-2xl border px-4 py-3">
                    <p
                        class="wm-text-muted text-[11px] font-semibold tracking-[0.14em] uppercase"
                    >
                        Total Pemanfaatan
                    </p>
                    <p
                        class="wm-text-primary mt-2 text-2xl font-semibold tracking-tight"
                    >
                        {{ formatNumber(totalUtilization) }}
                        <span class="wm-text-muted text-sm font-medium"
                            >ton</span
                        >
                    </p>
                </div>
                <div class="wm-surface-subtle rounded-2xl border px-4 py-3">
                    <p
                        class="wm-text-muted text-[11px] font-semibold tracking-[0.14em] uppercase"
                    >
                        Saldo Terakhir
                    </p>
                    <p
                        class="wm-text-primary mt-2 text-2xl font-semibold tracking-tight"
                    >
                        {{ formatNumber(latestBalance) }}
                        <span class="wm-text-muted text-sm font-medium"
                            >ton</span
                        >
                    </p>
                </div>
            </div>

            <div class="wm-panel rounded-2xl border p-4">
                <div class="grid grid-cols-[48px_minmax(0,1fr)] gap-4">
                    <div class="relative h-72">
                        <div
                            v-for="tick in chartTicks"
                            :key="tick.value"
                            class="wm-text-muted absolute right-0 left-0 text-[11px] font-medium"
                            :style="{
                                top: `${tick.percent}%`,
                                transform: 'translateY(-50%)',
                            }"
                        >
                            {{ formatNumber(tick.value) }}
                        </div>
                    </div>

                    <div
                        class="wm-panel-elevated relative h-72 rounded-xl border px-3 py-2"
                    >
                        <div
                            v-for="tick in chartTicks"
                            :key="`line-${tick.value}`"
                            class="absolute inset-x-3 border-t border-dashed border-slate-200 dark:border-slate-800"
                            :style="{ top: `${tick.percent}%` }"
                        />

                        <div
                            class="flex h-full items-end justify-between gap-3 pt-3"
                        >
                            <div
                                v-for="item in data"
                                :key="`${item.year}-${item.month}`"
                                class="flex min-w-0 flex-1 flex-col items-center gap-3"
                            >
                                <div
                                    class="flex h-60 w-full items-end justify-center gap-2 sm:gap-3"
                                >
                                    <div
                                        class="w-7 rounded-t-[12px] transition-all duration-500 sm:w-9"
                                        :style="{
                                            height: barHeight(item.production),
                                            backgroundColor: productionBarColor,
                                        }"
                                    />
                                    <div
                                        class="w-7 rounded-t-[12px] transition-all duration-500 sm:w-9"
                                        :style="{
                                            height: barHeight(item.utilization),
                                            backgroundColor:
                                                utilizationBarColor,
                                        }"
                                    />
                                </div>

                                <div
                                    class="w-full rounded-xl border border-transparent px-2 py-1 text-center transition-colors duration-200 hover:border-slate-200 hover:bg-white/80 dark:hover:border-slate-800 dark:hover:bg-slate-950/70"
                                >
                                    <p
                                        class="wm-text-secondary text-xs font-semibold tracking-[0.04em]"
                                    >
                                        {{ item.label }}
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
