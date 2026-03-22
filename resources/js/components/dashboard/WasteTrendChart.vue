<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { WasteChartData } from '@/types';

interface Props {
    data: WasteChartData[];
}

const props = defineProps<Props>();

const maxValue = computed(() => {
    const maxRecords = Math.max(...props.data.map((d) => d.records_count), 0);
    const maxTransport = Math.max(...props.data.map((d) => d.transport_delivered_count), 0);
    return Math.max(maxRecords, maxTransport, 1);
});

const recordsBarColor = 'rgb(34, 197, 94, 0.8)';
const transportBarColor = 'rgb(249, 115, 22, 0.8)';

const totalRecords = computed(() =>
    props.data.reduce((sum, item) => sum + item.records_count, 0),
);

const totalTransported = computed(() =>
    props.data.reduce((sum, item) => sum + item.transport_delivered_count, 0),
);

const totalApproved = computed(() =>
    props.data.reduce((sum, item) => sum + item.approved_count, 0),
);
</script>

<template>
    <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="space-y-4 pb-4">
            <div>
                <CardTitle class="text-base sm:text-lg">
                    Pencatatan vs Pengangkutan Limbah
                </CardTitle>
                <CardDescription>
                    Perbandingan volume pencatatan limbah dan transportasi selesai
                    untuk membaca throughput operasional 6 bulan terakhir.
                </CardDescription>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                        Total catatan
                    </p>
                    <p class="mt-1 text-lg font-semibold text-emerald-900">
                        {{ totalRecords.toLocaleString('id-ID') }}
                    </p>
                </div>
                <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">
                        Total disetujui
                    </p>
                    <p class="mt-1 text-lg font-semibold text-blue-900">
                        {{ totalApproved.toLocaleString('id-ID') }}
                    </p>
                </div>
                <div class="rounded-xl border border-orange-100 bg-orange-50/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-700">
                        Total diangkut
                    </p>
                    <p class="mt-1 text-lg font-semibold text-orange-900">
                        {{ totalTransported.toLocaleString('id-ID') }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <div
                v-for="(item, index) in data"
                :key="index"
                class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-slate-900">
                        {{ item.label }}
                    </span>
                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 shadow-sm">
                        {{ item.approved_count }} disetujui
                    </span>
                </div>

                <div class="space-y-2">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-emerald-700">
                            <span>Pencatatan limbah</span>
                            <span>{{ item.records_count.toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :style="{
                                        width: `${(item.records_count / maxValue) * 100}%`,
                                        backgroundColor: recordsBarColor,
                                    }"
                                />
                            </div>
                            <span class="w-16 text-right text-xs font-medium text-slate-700">
                                {{ item.records_count }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-orange-700">
                            <span>Transport selesai</span>
                            <span>{{
                                item.transport_delivered_count.toLocaleString('id-ID')
                            }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :style="{
                                        width: `${(item.transport_delivered_count / maxValue) * 100}%`,
                                        backgroundColor: transportBarColor,
                                    }"
                                />
                            </div>
                            <span class="w-16 text-right text-xs font-medium text-slate-700">
                                {{ item.transport_delivered_count }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center gap-6 pt-2 text-xs">
                <div class="flex items-center gap-2">
                    <div
                        class="h-2 w-3 rounded-full"
                        :style="{ backgroundColor: recordsBarColor }"
                    />
                    <span class="text-muted-foreground">Catatan limbah</span>
                </div>
                <div class="flex items-center gap-2">
                    <div
                        class="h-2 w-3 rounded-full"
                        :style="{ backgroundColor: transportBarColor }"
                    />
                    <span class="text-muted-foreground">Transport selesai</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
