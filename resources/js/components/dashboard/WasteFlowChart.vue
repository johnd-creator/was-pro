<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface WasteFlowData {
    label: string;
    month: number;
    year: number;
    input_count: number;
    transported_count: number;
}

interface Props {
    data: WasteFlowData[];
}

const props = defineProps<Props>();

const maxValue = computed(() => {
    const maxInput = Math.max(...props.data.map((d) => d.input_count), 0);
    const maxTransported = Math.max(...props.data.map((d) => d.transported_count), 0);

    return Math.max(maxInput, maxTransported, 1);
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

const inputColor = 'rgb(15 118 110)';
const transportedColor = 'rgb(234 88 12)';

const totalInput = computed(() =>
    props.data.reduce((sum, item) => sum + item.input_count, 0),
);

const totalTransported = computed(() =>
    props.data.reduce((sum, item) => sum + item.transported_count, 0),
);

function barHeight(value: number): string {
    const chartBodyHeight = 224;

    return `${Math.max((value / maxValue.value) * chartBodyHeight, 8)}px`;
}
</script>

<template>
    <Card class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/70 to-teal-50/35 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-teal-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]">
        <CardHeader class="space-y-5 pb-4 sm:pb-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-teal-700/70">
                        Waste Insight
                    </p>
                    <CardTitle class="mt-2 text-lg tracking-tight sm:text-xl">
                        Input vs Limbah Diangkut
                    </CardTitle>
                    <CardDescription class="mt-2 max-w-xl text-sm text-slate-600 dark:text-slate-300">
                        6 bulan terakhir untuk membaca ritme pencatatan dan pengangkutan.
                    </CardDescription>
                </div>
                <div class="rounded-[24px] border border-white/80 bg-white/85 px-4 py-3 text-right shadow-sm shadow-slate-200/50 backdrop-blur dark:border-slate-800/80 dark:bg-slate-900/85 dark:shadow-none">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Rasio angkut
                    </p>
                    <p class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ totalInput > 0 ? ((totalTransported / totalInput) * 100).toFixed(0) : '0' }}%
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">6 bulan terakhir</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="grid flex-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-[22px] border border-teal-200/70 bg-white/75 p-3 shadow-sm shadow-teal-100/30 dark:border-teal-900/70 dark:bg-slate-900/70 dark:shadow-none">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-teal-700">
                            Input limbah
                        </p>
                        <p class="mt-1 text-lg font-semibold text-teal-900 dark:text-teal-100">
                            {{ totalInput.toLocaleString('id-ID') }} catatan
                        </p>
                    </div>
                    <div class="rounded-[22px] border border-orange-200/70 bg-white/75 p-3 shadow-sm shadow-orange-100/30 dark:border-orange-900/70 dark:bg-slate-900/70 dark:shadow-none">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-700">
                            Limbah diangkut
                        </p>
                        <p class="mt-1 text-lg font-semibold text-orange-900 dark:text-orange-100">
                            {{ totalTransported.toLocaleString('id-ID') }} pengangkutan
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-full border border-white/90 bg-white/90 px-4 py-2 text-xs shadow-sm shadow-slate-200/60 dark:border-slate-800/80 dark:bg-slate-900/85 dark:shadow-none">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: inputColor }" />
                        <span class="font-medium text-slate-600 dark:text-slate-300">Input</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: transportedColor }" />
                        <span class="font-medium text-slate-600 dark:text-slate-300">Diangkut</span>
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
                                                height: barHeight(item.input_count),
                                                backgroundColor: inputColor,
                                                boxShadow: '0 10px 24px -16px rgb(15 118 110 / 0.9)',
                                            }"
                                        ></div>
                                    </div>

                                    <div class="flex items-end gap-2">
                                        <div
                                            class="w-6 rounded-t-[14px] shadow-sm transition-all duration-500 sm:w-8"
                                            :style="{
                                                height: barHeight(item.transported_count),
                                                backgroundColor: transportedColor,
                                                boxShadow: '0 10px 24px -16px rgb(234 88 12 / 0.9)',
                                            }"
                                        ></div>
                                    </div>
                                </div>

                                <div class="rounded-[18px] border border-transparent px-2 py-2 text-center transition-all duration-200 hover:border-slate-200/80 hover:bg-white/70 dark:hover:border-slate-800 dark:hover:bg-slate-900/60">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-700 dark:text-slate-300">
                                        {{ item.label }}
                                    </p>
                                    <p class="mt-1 text-[11px] font-semibold text-teal-700">
                                        {{ item.input_count.toLocaleString('id-ID') }} masuk
                                    </p>
                                    <p class="mt-1 text-[11px] font-semibold text-orange-700">
                                        {{ item.transported_count.toLocaleString('id-ID') }} diangkut
                                    </p>
                                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                        Gap {{ (item.input_count - item.transported_count).toLocaleString('id-ID') }}
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
