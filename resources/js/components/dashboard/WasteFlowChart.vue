<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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
import type { WasteChartData } from '@/types';

interface Props {
    data: WasteChartData[];
}

const props = defineProps<Props>();

const totalInput = computed(() =>
    props.data.reduce((sum, item) => sum + item.approved_input_quantity, 0),
);
const totalCompleted = computed(() =>
    props.data.reduce((sum, item) => sum + item.hauled_quantity, 0),
);
const latestBacklog = computed(() => props.data.at(-1)?.closing_backlog_quantity ?? 0);
const latestBacklogCount = computed(() => props.data.at(-1)?.closing_backlog_count ?? 0);
const latestExpiredBacklog = computed(
    () => props.data.at(-1)?.expired_backlog_quantity ?? 0,
);
const chartUnit = computed(() => props.data.at(-1)?.unit ?? 'ton');
const hasOtherUnits = computed(() =>
    props.data.some((item) => item.other_units_count > 0),
);

const wasteChartOption = computed<DashboardEChartsOption>(() => {
    const labels = props.data.map((item) => item.label);
    const inputData = props.data.map((item) => item.approved_input_quantity);
    const completedData = props.data.map((item) => item.hauled_quantity);
    const backlogData = props.data.map((item) => item.closing_backlog_quantity);
    const expiredMarkers = props.data.map((item) =>
        item.expired_backlog_quantity > 0 ? item.closing_backlog_quantity : null,
    );
    const expiringMarkers = props.data.map((item) =>
        item.expiring_soon_backlog_quantity > 0 ? item.closing_backlog_quantity : null,
    );
    const leftMax = Math.max(...inputData, ...completedData, ...backlogData, 0);

    return {
        ...dashboardChartPreset,
        grid: {
            top: 24,
            right: 24,
            bottom: 72,
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
        yAxis: {
            type: 'value' as const,
            min: 0,
            max: Math.ceil(leftMax * 1.15),
            name: chartUnit.value,
            nameLocation: 'end' as const,
            nameGap: 18,
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
        tooltip: {
            ...(dashboardChartPreset.tooltip as object),
            formatter: (params) => {
                const entries = (Array.isArray(params) ? params : [params]) as Array<{
                    axisValue?: string | number;
                    axisValueLabel?: string;
                    dataIndex?: number;
                    seriesName?: string;
                    value?: unknown;
                }>;

                if (!entries.length) {
                    return '';
                }

                const index = entries[0].dataIndex ?? 0;
                const row = props.data[index];
                const lines = [
                    `<div style="font-weight:700;margin-bottom:6px;">${entries[0].axisValueLabel ?? entries[0].axisValue ?? ''}</div>`,
                ];

                for (const point of entries) {
                    if (point.seriesName === 'Risiko Expired' || point.seriesName === 'Risiko Mendekati Batas') {
                        continue;
                    }

                    const value = typeof point.value === 'number' ? point.value : Number(point.value ?? 0);
                    const normalizedValue = Number.isFinite(value) ? value : 0;
                    lines.push(
                        `<div style="display:flex;justify-content:space-between;gap:16px;"><span>${point.seriesName ?? '-'}</span><strong>${formatNumber(normalizedValue)} ${chartUnit.value}</strong></div>`,
                    );
                }

                lines.push(
                    `<div style="display:flex;justify-content:space-between;gap:16px;"><span>Backlog expired</span><strong>${formatNumber(row?.expired_backlog_quantity ?? 0)} ${chartUnit.value}</strong></div>`,
                );
                lines.push(
                    `<div style="display:flex;justify-content:space-between;gap:16px;"><span>Mendekati batas</span><strong>${formatNumber(row?.expiring_soon_backlog_quantity ?? 0)} ${chartUnit.value}</strong></div>`,
                );

                return lines.join('');
            },
        },
        series: [
            {
                name: 'Intake approved',
                type: 'bar',
                barMaxWidth: 22,
                itemStyle: {
                    color: dashboardChartPalette.intake,
                    borderRadius: [6, 6, 0, 0],
                },
                data: inputData,
            },
            {
                name: 'Selesai diangkut',
                type: 'bar',
                barMaxWidth: 22,
                itemStyle: {
                    color: dashboardChartPalette.completed,
                    borderRadius: [6, 6, 0, 0],
                },
                data: completedData,
            },
            {
                name: 'Backlog akhir',
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: {
                    color: dashboardChartPalette.backlog,
                    width: 2.5,
                },
                itemStyle: {
                    color: '#ffffff',
                    borderColor: dashboardChartPalette.backlog,
                    borderWidth: 2,
                },
                data: backlogData,
            },
            {
                name: 'Risiko Expired',
                type: 'scatter',
                symbolSize: 8,
                itemStyle: {
                    color: dashboardChartPalette.critical,
                },
                data: expiredMarkers,
                tooltip: { show: false },
            },
            {
                name: 'Risiko Mendekati Batas',
                type: 'scatter',
                symbolSize: 8,
                itemStyle: {
                    color: dashboardChartPalette.warning,
                },
                data: expiringMarkers,
                tooltip: { show: false },
            },
        ],
    } as DashboardEChartsOption;
});

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID');
}

function formatQuantity(value: number): string {
    return `${formatNumber(value)} ${chartUnit.value}`;
}
</script>

<template>
    <Card
        class="wm-panel-elevated wm-card-overlay-blue overflow-hidden rounded-2xl border shadow-sm dark:border-slate-700/50 dark:bg-slate-900/40"
    >
        <CardHeader
            class="wm-border-default border-b bg-slate-50/40 pb-4 dark:border-slate-700/50 dark:bg-slate-900/60"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-1.5">
                    <CardTitle class="wm-text-primary text-base">
                        Intake Approved, Penyelesaian, dan Backlog Limbah
                    </CardTitle>
                    <CardDescription class="wm-text-secondary text-sm leading-6">
                        Memantau kuantitas limbah approved yang masuk, kuantitas terangkut, posisi backlog akhir, dan
                        risiko batas simpan tiap periode.
                    </CardDescription>
                    <p
                        v-if="hasOtherUnits"
                        class="text-xs font-medium text-amber-700 dark:text-amber-300"
                    >
                        Chart kuantitas memakai unit dominan {{ chartUnit }}. Unit lain tetap dihitung pada record dan
                        backlog.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: dashboardChartPalette.intake }" />
                        <span class="wm-text-secondary font-medium">Intake approved</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: dashboardChartPalette.completed }" />
                        <span class="wm-text-secondary font-medium">Selesai diangkut</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-0.5 w-4 rounded-full" :style="{ backgroundColor: dashboardChartPalette.backlog }" />
                        <span class="wm-text-secondary font-medium">Backlog akhir</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: dashboardChartPalette.critical }" />
                        <span class="wm-text-secondary font-medium">Expired</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: dashboardChartPalette.warning }" />
                        <span class="wm-text-secondary font-medium">Mendekati batas</span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-5 pt-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <Link
                    :href="wasteManagementRoutes.records.index.url()"
                    class="wm-surface-subtle rounded-2xl border px-4 py-3 transition-colors hover:border-teal-200 hover:bg-white dark:hover:border-teal-900 dark:hover:bg-slate-950/80"
                >
                    <p class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase">Limbah Masuk</p>
                    <p class="wm-text-primary mt-2 text-2xl font-semibold tracking-tight">
                        {{ formatQuantity(totalInput) }}
                    </p>
                </Link>

                <Link
                    :href="wasteManagementRoutes.haulings.index.url()"
                    class="wm-surface-subtle rounded-2xl border px-4 py-3 transition-colors hover:border-amber-200 hover:bg-white dark:hover:border-amber-900 dark:hover:bg-slate-950/80"
                >
                    <p class="wm-text-muted text-[11px] font-semibold tracking-[0.14em] uppercase">Terangkut</p>
                    <p class="wm-text-primary mt-2 text-2xl font-semibold tracking-tight">
                        {{ formatQuantity(totalCompleted) }}
                    </p>
                </Link>

                <Link
                    :href="wasteManagementRoutes.haulings.index.url()"
                    class="wm-surface-subtle rounded-2xl border px-4 py-3 transition-colors hover:border-red-200 hover:bg-white dark:hover:border-red-900 dark:hover:bg-slate-950/80"
                >
                    <p class="wm-text-muted text-[11px] font-semibold tracking-[0.14em] uppercase">Backlog Expired</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-red-700 dark:text-red-300">
                        {{ formatQuantity(latestExpiredBacklog) }}
                    </p>
                    <p class="wm-text-muted mt-1 text-xs">
                        Backlog akhir {{ formatQuantity(latestBacklog) }} dari {{ formatNumber(latestBacklogCount) }}
                        record
                    </p>
                </Link>
            </div>

            <div class="wm-panel rounded-2xl border p-3">
                <BaseEChart :option="wasteChartOption" height-class="h-[320px] sm:h-[340px]" />
            </div>
        </CardContent>
    </Card>
</template>
