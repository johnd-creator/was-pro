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

const productionBarColor = 'rgb(16, 185, 129, 0.8)';
const utilizationBarColor = 'rgb(59, 130, 246, 0.8)';

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
        <CardHeader class="space-y-4 pb-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <CardTitle class="text-base sm:text-lg">
                        Produksi vs Pemanfaatan FABA
                    </CardTitle>
                    <CardDescription>
                        Tren 6 bulan terakhir untuk membaca keseimbangan suplai,
                        pemanfaatan, dan saldo akhir TPS.
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

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                        Produksi
                    </p>
                    <p class="mt-1 text-lg font-semibold text-emerald-900">
                        {{ totalProduction.toLocaleString('id-ID') }} ton
                    </p>
                </div>
                <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">
                        Pemanfaatan
                    </p>
                    <p class="mt-1 text-lg font-semibold text-blue-900">
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
                        Saldo {{ item.closing_balance.toLocaleString('id-ID') }} ton
                    </span>
                </div>

                <div class="space-y-2">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-emerald-700">
                            <span>Produksi</span>
                            <span>{{ item.production.toLocaleString('id-ID') }} ton</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :style="{
                                        width: `${(item.production / maxValue) * 100}%`,
                                        backgroundColor: productionBarColor,
                                    }"
                                />
                            </div>
                            <span class="w-16 text-right text-xs font-medium text-slate-700">
                                {{ item.production.toLocaleString('id-ID') }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-blue-700">
                            <span>Pemanfaatan</span>
                            <span>{{ item.utilization.toLocaleString('id-ID') }} ton</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :style="{
                                        width: `${(item.utilization / maxValue) * 100}%`,
                                        backgroundColor: utilizationBarColor,
                                    }"
                                />
                            </div>
                            <span class="w-16 text-right text-xs font-medium text-slate-700">
                                {{ item.utilization.toLocaleString('id-ID') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center gap-6 pt-2 text-xs">
                <div class="flex items-center gap-2">
                    <div
                        class="h-2 w-3 rounded-full"
                        :style="{ backgroundColor: productionBarColor }"
                    />
                    <span class="text-muted-foreground">Produksi</span>
                </div>
                <div class="flex items-center gap-2">
                    <div
                        class="h-2 w-3 rounded-full"
                        :style="{ backgroundColor: utilizationBarColor }"
                    />
                    <span class="text-muted-foreground">Pemanfaatan</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
