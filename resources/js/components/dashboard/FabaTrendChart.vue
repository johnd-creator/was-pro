<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BarChart3, Gauge, Recycle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BaseEChart from '@/components/dashboard/BaseEChart.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DashboardEChartsOption } from '@/composables/useDashboardEChart';
import { dashboardChartPalette, dashboardChartPreset } from '@/composables/useDashboardEChart';
import wasteManagementRoutes from '@/routes/waste-management';
import type { FabaChartData } from '@/types';

interface Props {
    data: FabaChartData[];
}

const props = defineProps<Props>();

const selectedMaterial = ref<'total' | 'fly_ash' | 'bottom_ash'>('total');

const totalProduction = computed(() =>
    props.data.reduce((sum, item) => sum + item.production, 0),
);

const totalUtilization = computed(() =>
    props.data.reduce((sum, item) => sum + item.utilization, 0),
);

const warningPeriods = computed(() =>
    props.data.reduce((sum, item) => sum + (item.has_warning ? 1 : 0), 0),
);

const latestCapacity = computed(
    () => props.data.at(-1)?.capacity_utilization_percentage ?? 0,
);
const latestCapacityStatus = computed(() => props.data.at(-1)?.capacity_status ?? 'normal');
const capacityWarningThreshold = computed(
    () => props.data.at(-1)?.capacity_warning_threshold ?? 80,
);
const capacityCriticalThreshold = computed(
    () => props.data.at(-1)?.capacity_critical_threshold ?? 95,
);

const selectedMaterialRows = computed(() =>
    props.data.map((item) => {
        if (selectedMaterial.value === 'fly_ash') {
            return {
                ...item,
                material_label: 'Fly Ash',
                material_production: item.production_fly_ash,
                material_utilization: item.utilization_fly_ash,
                material_balance: item.closing_fly_ash,
            };
        }

        if (selectedMaterial.value === 'bottom_ash') {
            return {
                ...item,
                material_label: 'Bottom Ash',
                material_production: item.production_bottom_ash,
                material_utilization: item.utilization_bottom_ash,
                material_balance: item.closing_bottom_ash,
            };
        }

        return {
            ...item,
            material_label: 'Total FABA',
            material_production: item.production,
            material_utilization: item.utilization,
            material_balance: item.closing_balance,
        };
    }),
);

const fabaChartOption = computed<DashboardEChartsOption>(() => {
    const rows = selectedMaterialRows.value;
    const labels = rows.map((item) => item.label);
    const productionData = rows.map((item) => item.material_production);
    const utilizationData = rows.map((item) => item.material_utilization);
    const balanceData = rows.map((item) => item.material_balance);
    const capacityData = rows.map((item) => item.capacity_utilization_percentage);
    const tonMax = getNiceAxisMax(Math.max(...productionData, ...utilizationData, 0));
    const balanceMax = getNiceAxisMax(Math.max(...balanceData, 0));
    const rightMax = getNiceAxisMax(
        Math.max(...capacityData, capacityCriticalThreshold.value, 100),
    );

    return {
        ...dashboardChartPreset,
        grid: {
            top: 26,
            right: 56,
            bottom: 74,
            left: 52,
            containLabel: false,
        },
        xAxis: {
            type: 'category' as const,
            data: labels,
            axisLabel: {
                color: dashboardChartPalette.axisLabel,
                fontSize: 12,
                margin: 12,
                fontWeight: 600,
            },
            axisLine: {
                lineStyle: {
                    color: dashboardChartPalette.axisLine,
                    width: 1,
                },
            },
            axisTick: {
                show: false,
            },
        },
        yAxis: [
            {
                type: 'value' as const,
                min: 0,
                max: tonMax,
                name: 'Ton',
                nameLocation: 'end' as const,
                nameGap: 20,
                nameTextStyle: {
                    color: dashboardChartPalette.axisLabel,
                    fontSize: 11,
                    fontWeight: 600,
                },
                axisLabel: {
                    color: dashboardChartPalette.axisLabel,
                    fontSize: 12,
                    fontWeight: 500,
                },
                splitLine: {
                    lineStyle: {
                        color: dashboardChartPalette.gridLine,
                        type: 'dashed' as const,
                        width: 1,
                    },
                },
                axisLine: {
                    show: false,
                },
                axisTick: {
                    show: false,
                },
            },
            {
                type: 'value' as const,
                min: 0,
                max: rightMax,
                name: 'TPS (%)',
                position: 'right' as const,
                nameLocation: 'start' as const,
                nameGap: 18,
                splitLine: { show: false },
                axisLabel: {
                    color: dashboardChartPalette.axisLabel,
                    fontSize: 11,
                    fontWeight: 500,
                },
                axisLine: { show: false },
                axisTick: { show: false },
                nameTextStyle: {
                    color: dashboardChartPalette.capacity,
                    fontSize: 11,
                    fontWeight: 600,
                },
            },
            {
                type: 'value' as const,
                min: 0,
                max: balanceMax,
                show: false,
            },
        ],
        series: [
            {
                name: 'Produksi',
                type: 'bar',
                barMaxWidth: 22,
                itemStyle: {
                    color: dashboardChartPalette.production,
                    borderRadius: [6, 6, 0, 0],
                },
                data: productionData,
            },
            {
                name: 'Pemanfaatan',
                type: 'bar',
                barMaxWidth: 22,
                itemStyle: {
                    color: dashboardChartPalette.utilization,
                    borderRadius: [6, 6, 0, 0],
                },
                data: utilizationData,
            },
            {
                name: 'Saldo akhir',
                type: 'line',
                yAxisIndex: 2,
                smooth: true,
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: {
                    color: dashboardChartPalette.balance,
                    width: 2.5,
                },
                itemStyle: {
                    color: '#ffffff',
                    borderColor: dashboardChartPalette.balance,
                    borderWidth: 2,
                },
                data: balanceData,
            },
            {
                name: 'Utilisasi TPS',
                type: 'line',
                yAxisIndex: 1,
                smooth: true,
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: {
                    color: dashboardChartPalette.capacity,
                    width: 2.5,
                    type: 'dashed',
                },
                itemStyle: {
                    color: '#ffffff',
                    borderColor: dashboardChartPalette.capacity,
                    borderWidth: 2,
                },
                markLine: {
                    symbol: 'none',
                    label: { show: false },
                    lineStyle: {
                        type: 'dashed',
                        width: 1.5,
                        color: dashboardChartPalette.warning,
                    },
                    data: [
                        { yAxis: capacityWarningThreshold.value },
                        {
                            yAxis: capacityCriticalThreshold.value,
                            lineStyle: {
                                type: 'dashed',
                                width: 1.5,
                                color: dashboardChartPalette.critical,
                            },
                        },
                    ],
                },
                data: capacityData,
            },
        ],
        tooltip: {
            ...(dashboardChartPreset.tooltip as object),
            formatter: (params) => {
                const entries = (Array.isArray(params) ? params : [params]) as Array<{
                    axisValue?: string | number;
                    axisValueLabel?: string;
                    seriesName?: string;
                    value?: unknown;
                }>;

                if (!entries.length) {
                    return '';
                }

                const lines = [
                    `<div style="font-weight:700;margin-bottom:6px;">${entries[0].axisValueLabel ?? entries[0].axisValue ?? ''}</div>`,
                ];

                for (const point of entries) {
                    const value = typeof point.value === 'number' ? point.value : Number(point.value ?? 0);
                    const normalizedValue = Number.isFinite(value) ? value : 0;
                    const suffix = point.seriesName === 'Utilisasi TPS' ? '%' : ' ton';
                    lines.push(
                        `<div style="display:flex;justify-content:space-between;gap:16px;"><span>${point.seriesName ?? '-'}</span><strong>${formatNumber(normalizedValue)}${suffix}</strong></div>`,
                    );
                }

                return lines.join('');
            },
        },
    } as DashboardEChartsOption;
});

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID');
}

function getNiceAxisMax(value: number): number {
    if (value <= 0) {
        return 10;
    }

    const padded = value * 1.18;
    const magnitude = 10 ** Math.floor(Math.log10(padded));
    const normalized = padded / magnitude;
    const niceFactor =
        normalized <= 1
            ? 1
            : normalized <= 2
              ? 2
              : normalized <= 5
                ? 5
                : 10;

    return niceFactor * magnitude;
}

function formatPercentage(value: number): string {
    return `${value.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1,
    })}%`;
}
</script>

<template>
    <Card class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-700/50 dark:bg-slate-900/40">
        <CardHeader class="border-b border-slate-200/80 bg-slate-50/60 pb-4 dark:border-slate-700/50 dark:bg-slate-900/40">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-1.5">
                    <CardTitle class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                        Produksi, Pemanfaatan, dan Saldo FABA
                    </CardTitle>
                    <CardDescription class="max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-300">
                        Ringkasan tonase per bulan untuk membaca arus produksi, pemanfaatan, posisi saldo akhir,
                        utilisasi kapasitas TPS, dan periode yang memerlukan perhatian.
                    </CardDescription>
                </div>

                <div class="grid gap-2 text-xs sm:grid-cols-2">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: dashboardChartPalette.production }" />
                        <span class="font-semibold text-slate-700 dark:text-slate-200">Produksi</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: dashboardChartPalette.utilization }" />
                        <span class="font-semibold text-slate-700 dark:text-slate-200">Pemanfaatan</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-0.5 w-4 rounded-full" :style="{ backgroundColor: dashboardChartPalette.balance }" />
                        <span class="font-semibold text-slate-700 dark:text-slate-200">Saldo akhir</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-0.5 w-4 rounded-full border-t border-dashed" :style="{ borderColor: dashboardChartPalette.capacity }" />
                        <span class="font-semibold text-slate-700 dark:text-slate-200">Utilisasi TPS</span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-5 pt-6">
            <div class="grid gap-3 sm:grid-cols-3">
                <Link
                    :href="wasteManagementRoutes.faba.production.index.url()"
                    class="rounded-2xl border border-blue-200/80 bg-blue-50/40 px-4 py-3.5 transition-colors hover:bg-blue-50 dark:border-blue-900/50 dark:bg-blue-950/20"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">Total Produksi</p>
                            <p class="mt-2 text-2xl font-semibold leading-none tracking-tight text-slate-900 dark:text-slate-100">
                                {{ formatNumber(totalProduction) }}
                                <span class="ml-1 text-base font-medium text-slate-500">ton</span>
                            </p>
                        </div>
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-100/90 text-blue-500 dark:bg-blue-900/40">
                            <BarChart3 class="h-7 w-7" />
                        </div>
                    </div>
                </Link>

                <Link
                    :href="wasteManagementRoutes.faba.utilization.index.url()"
                    class="rounded-2xl border border-amber-200/80 bg-amber-50/40 px-4 py-3.5 transition-colors hover:bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">Total Pemanfaatan</p>
                            <p class="mt-2 text-2xl font-semibold leading-none tracking-tight text-slate-900 dark:text-slate-100">
                                {{ formatNumber(totalUtilization) }}
                                <span class="ml-1 text-base font-medium text-slate-500">ton</span>
                            </p>
                        </div>
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-amber-100/90 text-amber-500 dark:bg-amber-900/40">
                            <Recycle class="h-7 w-7" />
                        </div>
                    </div>
                </Link>

                <Link
                    :href="wasteManagementRoutes.faba.recaps.balance.url()"
                    class="rounded-2xl border border-red-200/80 bg-red-50/40 px-4 py-3.5 transition-colors hover:bg-red-50 dark:border-red-900/50 dark:bg-red-950/20"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">Utilisasi TPS</p>
                            <p
                                class="mt-2 text-2xl font-semibold leading-none tracking-tight"
                                :class="
                                    latestCapacityStatus === 'critical'
                                        ? 'text-red-600 dark:text-red-400'
                                        : latestCapacityStatus === 'warning'
                                          ? 'text-orange-600 dark:text-orange-400'
                                          : 'text-slate-900 dark:text-slate-100'
                                "
                            >
                                {{ formatPercentage(latestCapacity) }}
                            </p>
                            <p class="mt-1 text-base text-slate-500">{{ formatNumber(warningPeriods) }} bulan warning</p>
                        </div>
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-red-100/90 text-red-500 dark:bg-red-900/40">
                            <Gauge class="h-7 w-7" />
                        </div>
                    </div>
                </Link>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-base font-semibold transition-colors"
                    :class="
                        selectedMaterial === 'total'
                            ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-950'
                            : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                    "
                    @click="selectedMaterial = 'total'"
                >
                    Total
                </button>
                <button
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-base font-semibold transition-colors"
                    :class="
                        selectedMaterial === 'fly_ash'
                            ? 'border-blue-700 bg-blue-700 text-white'
                            : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                    "
                    @click="selectedMaterial = 'fly_ash'"
                >
                    Fly Ash
                </button>
                <button
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-base font-semibold transition-colors"
                    :class="
                        selectedMaterial === 'bottom_ash'
                            ? 'border-amber-700 bg-amber-700 text-white'
                            : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                    "
                    @click="selectedMaterial = 'bottom_ash'"
                >
                    Bottom Ash
                </button>
            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-3 dark:border-slate-700/50 dark:bg-slate-900/40">
                <BaseEChart :option="fabaChartOption" height-class="h-[330px] sm:h-[350px]" />
                <div class="mt-4 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 border-t border-slate-200/80 pt-4 text-sm dark:border-slate-700/50">
                    <div class="flex items-center gap-2">
                        <span class="h-4 w-4 rounded bg-blue-600" />
                        <span class="text-slate-600 dark:text-slate-300">Produksi (ton)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-4 w-4 rounded bg-amber-500" />
                        <span class="text-slate-600 dark:text-slate-300">Pemanfaatan (ton)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-0.5 w-7 rounded-full bg-emerald-600" />
                        <span class="text-slate-600 dark:text-slate-300">Saldo akhir (ton)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-0.5 w-7 rounded-full border-t-2 border-dashed border-red-600" />
                        <span class="text-slate-600 dark:text-slate-300">Utilisasi TPS (%)</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
