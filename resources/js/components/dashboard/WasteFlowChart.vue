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
    <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="space-y-5 pb-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <CardTitle class="text-base sm:text-lg">
                        Input vs Limbah Diangkut
                    </CardTitle>
                    <CardDescription>
                        Tren 6 bulan terakhir untuk memantau keseimbangan pencatatan
                        limbah masuk dengan limbah yang sudah terkirim.
                    </CardDescription>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-right">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        Rasio angkut
                    </p>
                    <p class="text-2xl font-semibold text-slate-900">
                        {{ totalInput > 0 ? ((totalTransported / totalInput) * 100).toFixed(0) : '0' }}%
                    </p>
                    <p class="text-xs text-slate-500">6 bulan terakhir</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="grid flex-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-teal-100 bg-teal-50/70 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-teal-700">
                            Input limbah
                        </p>
                        <p class="mt-1 text-lg font-semibold text-teal-900">
                            {{ totalInput.toLocaleString('id-ID') }} catatan
                        </p>
                    </div>
                    <div class="rounded-xl border border-orange-100 bg-orange-50/70 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-700">
                            Limbah diangkut
                        </p>
                        <p class="mt-1 text-lg font-semibold text-orange-900">
                            {{ totalTransported.toLocaleString('id-ID') }} pengangkutan
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: inputColor }" />
                        <span class="font-medium text-slate-600">Input</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: transportedColor }" />
                        <span class="font-medium text-slate-600">Diangkut</span>
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
                                <div class="flex h-56 w-full items-end justify-center gap-2 sm:gap-3">
                                    <div class="flex items-end gap-2">
                                        <div
                                            class="w-6 rounded-t-lg shadow-sm transition-all duration-500 sm:w-8"
                                            :style="{
                                                height: barHeight(item.input_count),
                                                backgroundColor: inputColor,
                                            }"
                                        ></div>
                                    </div>

                                    <div class="flex items-end gap-2">
                                        <div
                                            class="w-6 rounded-t-lg shadow-sm transition-all duration-500 sm:w-8"
                                            :style="{
                                                height: barHeight(item.transported_count),
                                                backgroundColor: transportedColor,
                                            }"
                                        ></div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">
                                        {{ item.label }}
                                    </p>
                                    <p class="mt-1 text-[10px] font-semibold text-teal-700">
                                        Input {{ item.input_count.toLocaleString('id-ID') }}
                                    </p>
                                    <p class="mt-1 text-[10px] font-semibold text-orange-700">
                                        Diangkut {{ item.transported_count.toLocaleString('id-ID') }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Selisih {{ (item.input_count - item.transported_count).toLocaleString('id-ID') }}
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
