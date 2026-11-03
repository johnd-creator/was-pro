<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
import {
    dashboardChartPalette,
    dashboardChartPreset,
} from '@/composables/useDashboardEChart';
import wasteManagementRoutes from '@/routes/waste-management';
import type { WasteChartData } from '@/types';

interface Props {
    data: WasteChartData[];
    organizationId?: string | null;
}

const props = defineProps<Props>();

const selectedUnit = ref('');
const availableUnits = computed(() =>
    Object.keys(props.data.at(-1)?.unit_series ?? {}),
);
const rows = computed(() =>
    props.data.map((row) => row.unit_series?.[selectedUnit.value] ?? row),
);
const latestSoon = computed(
    () => rows.value.at(-1)?.expiring_soon_backlog_quantity ?? 0,
);
const backlogChange = computed(
    () =>
        (rows.value.at(-1)?.closing_backlog_quantity ?? 0) -
        (rows.value.at(-2)?.closing_backlog_quantity ?? 0),
);

const totalInput = computed(() =>
    rows.value.reduce((sum, item) => sum + item.approved_input_quantity, 0),
);
const totalCompleted = computed(() =>
    rows.value.reduce((sum, item) => sum + item.hauled_quantity, 0),
);
const latestBacklog = computed(
    () => rows.value.at(-1)?.closing_backlog_quantity ?? 0,
);
const latestBacklogCount = computed(
    () => rows.value.at(-1)?.active_backlog_unit_count ?? 0,
);
const latestExpiredBacklog = computed(
    () => rows.value.at(-1)?.expired_backlog_quantity ?? 0,
);
const chartUnit = computed(() => rows.value.at(-1)?.unit ?? 'ton');
const hasOtherUnits = computed(() =>
    rows.value.some((item) => item.other_units_count > 0),
);

const latest = computed(() => rows.value.at(-1));
const expiredHref = computed(() =>
    wasteManagementRoutes.records.index.url({
        query: {
            expiry: 'expired',
            as_of: latest.value?.snapshot_date,
            unit: chartUnit.value,
            organization_id: props.organizationId ?? undefined,
        },
    }),
);
const kpis = computed(() => [
    {
        label: 'Limbah Masuk',
        value: totalInput.value,
        context: 'Approved · enam bulan',
        tone: 'wm-text-primary',
    },
    {
        label: 'Terangkut',
        value: totalCompleted.value,
        context: 'Approved · enam bulan',
        tone: 'wm-text-primary',
    },
    {
        label: 'Backlog Aktif',
        value: latestBacklog.value,
        context: `${latestBacklogCount.value} catatan · snapshot terakhir`,
        tone: 'text-blue-700 dark:text-blue-300',
    },
    {
        label: 'Backlog Expired',
        value: latestExpiredBacklog.value,
        context: `${latest.value?.expired_backlog_count ?? 0} catatan · ${latestExpiredBacklog.value > 0 ? 'perlu penanganan' : 'snapshot terakhir'}`,
        tone:
            latestExpiredBacklog.value > 0
                ? 'text-red-700 dark:text-red-300'
                : 'wm-text-primary',
    },
]);

const wasteChartOption = computed<DashboardEChartsOption>(() => {
    const labels = rows.value.map((item) => item.label);
    const inputData = rows.value.map((item) => item.approved_input_quantity);
    const completedData = rows.value.map((item) => item.hauled_quantity);
    const backlogData = rows.value.map((item) => item.closing_backlog_quantity);
    const expiredMarkers = rows.value.map((item) =>
        item.expired_backlog_quantity > 0
            ? item.closing_backlog_quantity
            : null,
    );
    const expiringMarkers = rows.value.map((item) =>
        item.expiring_soon_backlog_quantity > 0
            ? item.closing_backlog_quantity
            : null,
    );
    const leftMax = Math.max(...inputData, ...completedData, ...backlogData, 0);

    return {
        ...dashboardChartPreset,
        grid: {
            top: 30,
            right: 12,
            bottom: 12,
            left: 8,
            containLabel: true,
        },
        xAxis: {
            type: 'category' as const,
            data: labels,
            axisLabel: {
                color: dashboardChartPalette.axisLabel,
                fontSize: 10,
                hideOverlap: true,
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
            max: Math.max(1, Math.ceil(leftMax * 1.15)),
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
                formatter: (value: number) =>
                    new Intl.NumberFormat('id-ID', {
                        notation: 'compact',
                        maximumFractionDigits: 1,
                    }).format(value),
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
            confine: true,
            appendToBody: false,
            formatter: (params) => {
                const entries = (
                    Array.isArray(params) ? params : [params]
                ) as Array<{
                    axisValue?: string | number;
                    axisValueLabel?: string;
                    dataIndex?: number;
                    seriesName?: string;
                    marker?: string;
                    value?: unknown;
                }>;

                if (!entries.length) {
                    return '';
                }

                const index = entries[0].dataIndex ?? 0;
                const row = rows.value[index];
                const lines = [
                    `<div style="font-weight:700;margin-bottom:6px;">${entries[0].axisValueLabel ?? entries[0].axisValue ?? ''}</div>`,
                ];

                for (const point of entries) {
                    if (
                        point.seriesName === 'Risiko Expired' ||
                        point.seriesName === 'Risiko Mendekati Batas'
                    ) {
                        continue;
                    }

                    const value =
                        typeof point.value === 'number'
                            ? point.value
                            : Number(point.value ?? 0);
                    const normalizedValue = Number.isFinite(value) ? value : 0;
                    lines.push(
                        `<div style="display:flex;justify-content:space-between;gap:16px;"><span>${tooltipMarker(point.seriesName ?? '')}${point.seriesName ?? '-'}</span><strong>${formatNumber(normalizedValue)} ${chartUnit.value}</strong></div>`,
                    );
                }

                lines.push(
                    `<div style="display:flex;justify-content:space-between;gap:16px;"><span>${tooltipMarker('Risiko Expired')}Backlog expired</span><strong>${formatNumber(row?.expired_backlog_quantity ?? 0)} ${chartUnit.value}</strong></div>`,
                );
                lines.push(
                    `<div style="display:flex;justify-content:space-between;gap:16px;"><span>${tooltipMarker('Risiko Mendekati Batas')}Mendekati batas</span><strong>${formatNumber(row?.expiring_soon_backlog_quantity ?? 0)} ${chartUnit.value}</strong></div>`,
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
                smooth: false,
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
                markLine: {
                    silent: true,
                    symbol: 'none',
                    label: { show: false },
                    lineStyle: {
                        color: dashboardChartPalette.backlog,
                        opacity: 0.35,
                        type: 'dashed',
                    },
                    data: rows.value.length
                        ? [{ xAxis: rows.value.at(-1)!.label }]
                        : [],
                },
            },
            {
                name: 'Risiko Expired',
                type: 'scatter',
                symbolSize: 8,
                symbolOffset: [-6, -5],
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
                symbolOffset: [6, 5],
                itemStyle: {
                    color: dashboardChartPalette.warning,
                },
                data: expiringMarkers,
                tooltip: { show: false },
            },
        ],
    } as DashboardEChartsOption;
});

function tooltipMarker(series: string): string {
    const colors: Record<string, string> = {
        'Intake approved': dashboardChartPalette.intake,
        'Selesai diangkut': dashboardChartPalette.completed,
        'Backlog akhir': dashboardChartPalette.backlog,
        'Risiko Expired': dashboardChartPalette.critical,
        'Risiko Mendekati Batas': dashboardChartPalette.warning,
    };
    return `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px;background:${colors[series] ?? dashboardChartPalette.axisLabel}"></span>`;
}

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function formatQuantity(value: number): string {
    return `${formatNumber(value)} ${chartUnit.value}`;
}
</script>

<template>
    <Card
        class="wm-panel-elevated wm-card-overlay-blue min-w-0 overflow-hidden rounded-2xl border shadow-sm dark:border-slate-700/50 dark:bg-slate-900/40"
    >
        <CardHeader
            class="wm-border-default border-b bg-slate-50/40 pb-4 dark:border-slate-700/50 dark:bg-slate-900/60"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="space-y-1.5">
                    <CardTitle class="wm-text-primary text-base">
                        Intake Approved, Penyelesaian, dan Backlog Limbah
                    </CardTitle>
                    <CardDescription
                        class="wm-text-secondary text-sm leading-6"
                    >
                        Memantau kuantitas limbah approved yang masuk, kuantitas
                        terangkut, posisi backlog akhir, dan risiko batas simpan
                        tiap periode.
                    </CardDescription>
                    <p
                        v-if="hasOtherUnits"
                        class="text-xs font-medium text-amber-700 dark:text-amber-300"
                    >
                        Kuantitas dan jumlah catatan KPI mengikuti satuan
                        {{ chartUnit }}. Distribusi mencakup semua satuan.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{
                                backgroundColor: dashboardChartPalette.intake,
                            }"
                        />
                        <span class="wm-text-secondary font-medium"
                            >Intake approved</span
                        >
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{
                                backgroundColor:
                                    dashboardChartPalette.completed,
                            }"
                        />
                        <span class="wm-text-secondary font-medium"
                            >Selesai diangkut</span
                        >
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-0.5 w-4 rounded-full"
                            :style="{
                                backgroundColor: dashboardChartPalette.backlog,
                            }"
                        />
                        <span class="wm-text-secondary font-medium"
                            >Backlog akhir</span
                        >
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-2 w-2 rounded-full"
                            :style="{
                                backgroundColor: dashboardChartPalette.critical,
                            }"
                        />
                        <span class="wm-text-secondary font-medium"
                            >Expired</span
                        >
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-2 w-2 rounded-full"
                            :style="{
                                backgroundColor: dashboardChartPalette.warning,
                            }"
                        />
                        <span class="wm-text-secondary font-medium"
                            >Mendekati batas</span
                        >
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pt-4">
            <div
                class="wm-text-secondary flex flex-wrap items-center gap-3 text-sm"
            >
                <label
                    v-if="availableUnits.length > 1"
                    class="flex items-center gap-2"
                    >Satuan
                    <select
                        v-model="selectedUnit"
                        class="wm-panel rounded-lg border px-3 py-2"
                    >
                        <option value="">{{ data.at(-1)?.unit }} (awal)</option>
                        <option
                            v-for="unit in availableUnits"
                            :key="unit"
                            :value="unit"
                        >
                            {{ unit }}
                        </option>
                    </select>
                </label>
                <span
                    >{{ rows[0]?.label }} - {{ rows.at(-1)?.label }} · enam
                    bulan</span
                >
                <span class="text-xs">{{
                    latest?.snapshot_label ?? 'Snapshot belum tersedia'
                }}</span>
            </div>
            <p
                v-if="
                    !rows.some(
                        (row) =>
                            row.approved_input_quantity ||
                            row.hauled_quantity ||
                            row.closing_backlog_quantity,
                    )
                "
                class="wm-text-muted text-sm"
            >
                Belum ada kuantitas pada satuan dan periode ini.
            </p>
            <div
                class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4"
                data-testid="waste-kpis"
            >
                <div
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    class="wm-surface-subtle flex min-w-0 flex-col gap-2 rounded-xl border p-4"
                >
                    <p class="wm-text-secondary text-xs font-semibold">
                        {{ kpi.label }}
                    </p>
                    <p
                        :class="[
                            'text-2xl font-semibold tracking-tight tabular-nums',
                            kpi.tone,
                        ]"
                    >
                        {{ formatNumber(kpi.value) }}
                        <span class="text-sm font-medium">{{ chartUnit }}</span>
                    </p>
                    <p class="wm-text-muted mt-auto text-xs">
                        {{ kpi.context }}
                    </p>
                </div>
            </div>
            <div
                class="flex flex-wrap items-start justify-between gap-3 rounded-xl border p-3 text-sm"
                :class="
                    latestExpiredBacklog > 0
                        ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200'
                        : 'wm-surface-subtle wm-text-secondary'
                "
                role="status"
            >
                <div class="space-y-1">
                    <p class="font-semibold">
                        {{
                            latestExpiredBacklog > 0
                                ? `${formatQuantity(latestExpiredBacklog)} expired dari ${formatQuantity(latestBacklog)} backlog aktif (${latest?.expired_backlog_count ?? 0} catatan). Prioritaskan pengangkutan.`
                                : 'Tidak ada backlog expired pada snapshot ini.'
                        }}
                    </p>
                    <p class="text-xs">
                        {{ latest?.snapshot_label }} · {{ chartUnit }} · catatan
                        pada halaman tujuan mengikuti hak akses Anda.
                    </p>
                    <p class="text-xs">
                        Backlog
                        {{
                            backlogChange > 0
                                ? 'naik'
                                : backlogChange < 0
                                  ? 'turun'
                                  : 'tetap'
                        }}
                        {{ formatQuantity(Math.abs(backlogChange)) }} dibanding
                        bulan sebelumnya. Mendekati batas (7 hari):
                        {{ formatQuantity(latestSoon) }}.
                    </p>
                </div>
                <Link
                    v-if="latestExpiredBacklog > 0"
                    :href="expiredHref"
                    class="shrink-0 rounded-lg border border-current px-3 py-2 text-xs font-semibold"
                    >Lihat Limbah Expired</Link
                >
            </div>

            <div class="wm-panel rounded-2xl border p-3">
                <BaseEChart
                    :option="wasteChartOption"
                    height-class="h-[270px] sm:h-[300px]"
                />
            </div>
        </CardContent>
    </Card>
</template>
