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
const utilizationBarColor = 'rgb(49 85 183)';

const totalProduction = computed(() =>
    props.data.reduce((sum, item) => sum + item.production, 0),
);

const totalUtilization = computed(() =>
    props.data.reduce((sum, item) => sum + item.utilization, 0),
);

const latestBalance = computed(() => props.data.at(-1)?.closing_balance ?? 0);
</script>

<template>
    <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="space-y-5 pb-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <CardTitle class="text-base sm:text-lg">
                        Produksi vs Pemanfaatan FABA
                    </CardTitle>
                    <CardDescription>
                        Ringkasan 6 bulan terakhir untuk membaca keseimbangan
                        suplai, pemanfaatan, dan saldo akhir TPS.
                    </CardDescription>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-right"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        Saldo akhir terbaru
                    </p>
                    <p class="text-2xl font-semibold text-slate-900">
                        {{ latestBalance.toLocaleString('id-ID') }}
                    </p>
                    <p class="text-xs text-slate-500">ton</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="grid flex-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">
                            Produksi
                        </p>
                        <p class="mt-1 text-lg font-semibold text-blue-900">
                            {{ totalProduction.toLocaleString('id-ID') }} ton
                        </p>
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700">
                            Pemanfaatan
                        </p>
                        <p class="mt-1 text-lg font-semibold text-indigo-900">
                            {{ totalUtilization.toLocaleString('id-ID') }} ton
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                            Periode
                        </p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">
                            {{ props.data.length }} bulan
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs shadow-sm">
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: productionBarColor }"
                        />
                        <span class="font-medium text-slate-600">Produksi</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: utilizationBarColor }"
                        />
                        <span class="font-medium text-slate-600">
                            Pemanfaatan
                        </span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent>
            <div class="rounded-3xl border border-slate-100 bg-gradient-to-b from-white to-slate-50/70 p-5">
                <div class="grid grid-cols-[44px_minmax(0,1fr)] gap-4">
                    <div class="relative h-72">
                        <div
                            v-for="tick in chartTicks"
                            :key="tick.value"
                            class="absolute left-0 right-0 text-[11px] font-medium text-slate-400"
                            :style="{ top: `${tick.percent}%`, transform: 'translateY(-50%)' }"
                        >
                            {{ tick.value.toLocaleString('id-ID') }}
                        </div>
                    </div>

                    <div class="relative h-72">
                        <div
                            v-for="tick in chartTicks"
                            :key="`line-${tick.value}`"
                            class="absolute inset-x-0 border-t border-dashed border-slate-200"
                            :style="{ top: `${tick.percent}%` }"
                        />

                        <div class="flex h-full items-end justify-between gap-3">
                            <div
                                v-for="item in data"
                                :key="`${item.year}-${item.month}`"
                                class="flex min-w-0 flex-1 flex-col items-center gap-3"
                            >
                                <div class="flex h-full w-full items-end justify-center gap-2">
                                    <div class="flex flex-col items-center gap-2">
                                        <div
                                            class="w-5 rounded-t-md shadow-sm transition-all duration-500 sm:w-7"
                                            :style="{
                                                height: `${Math.max((item.production / maxValue) * 100, 3)}%`,
                                                backgroundColor: productionBarColor,
                                            }"
                                        />
                                        <span class="text-[10px] font-semibold text-blue-700">
                                            {{ item.production.toLocaleString('id-ID') }}
                                        </span>
                                    </div>

                                    <div class="flex flex-col items-center gap-2">
                                        <div
                                            class="w-5 rounded-t-md shadow-sm transition-all duration-500 sm:w-7"
                                            :style="{
                                                height: `${Math.max((item.utilization / maxValue) * 100, 3)}%`,
                                                backgroundColor: utilizationBarColor,
                                            }"
                                        />
                                        <span class="text-[10px] font-semibold text-indigo-700">
                                            {{ item.utilization.toLocaleString('id-ID') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">
                                        {{ item.label }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Saldo
                                        {{ item.closing_balance.toLocaleString('id-ID') }}
                                        ton
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
