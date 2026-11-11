<script setup lang="ts">
import { computed, ref } from 'vue';
import BaseEChart from '@/components/dashboard/BaseEChart.vue';
import Link from '@/components/OrganizationLink.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    dashboardChartPalette as palette,
    dashboardChartPreset,
} from '@/composables/useDashboardEChart';
import type { DashboardEChartsOption } from '@/composables/useDashboardEChart';
import wasteManagementRoutes from '@/routes/waste-management';
import type { FabaChartData } from '@/types';

const props = defineProps<{ data: FabaChartData[] }>();
const selectedMaterial = ref<'total' | 'fly_ash' | 'bottom_ash'>('total');
const materials = [
    { value: 'total', label: 'Total' },
    { value: 'fly_ash', label: 'Fly Ash' },
    { value: 'bottom_ash', label: 'Bottom Ash' },
] as const;
const rows = computed(() =>
    props.data.map((item) => ({
        label: `${item.label} ${item.year}`,
        production:
            selectedMaterial.value === 'total'
                ? item.production
                : selectedMaterial.value === 'fly_ash'
                  ? item.production_fly_ash
                  : item.production_bottom_ash,
        utilization:
            selectedMaterial.value === 'total'
                ? item.utilization
                : selectedMaterial.value === 'fly_ash'
                  ? item.utilization_fly_ash
                  : item.utilization_bottom_ash,
        capacity: item.capacity[selectedMaterial.value],
    })),
);
const latest = computed(() => rows.value.at(-1)?.capacity);
const period = computed(
    () => `${rows.value[0]?.label ?? '-'} - ${rows.value.at(-1)?.label ?? '-'}`,
);
const production = computed(() =>
    rows.value.reduce((sum, row) => sum + row.production, 0),
);
const utilization = computed(() =>
    rows.value.reduce((sum, row) => sum + row.utilization, 0),
);
const capacityWarnings = computed(
    () =>
        rows.value.filter(
            (row) => row.capacity.available && row.capacity.status !== 'normal',
        ).length,
);
const integrityWarnings = computed(
    () => props.data.filter((row) => row.has_warning).length,
);
const statusLabel = computed(() => {
    if (!latest.value?.available) return 'Kapasitas belum tersedia';
    if ((latest.value.utilization_percentage ?? 0) > 100)
        return 'Melebihi kapasitas';
    return {
        normal: 'Normal',
        warning: 'Perlu perhatian',
        critical: 'Kritis',
        unavailable: 'Belum tersedia',
    }[latest.value.status];
});
const statusClass = computed(() =>
    latest.value?.status === 'critical'
        ? 'text-red-700 dark:text-red-300'
        : latest.value?.status !== 'normal'
          ? 'text-amber-700 dark:text-amber-300'
          : 'text-emerald-700 dark:text-emerald-300',
);
function number(value: number | null | undefined): string {
    return value == null
        ? 'Tidak tersedia'
        : value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}
function chart(
    unit: string,
    series: DashboardEChartsOption['series'],
): DashboardEChartsOption {
    return {
        ...dashboardChartPreset,
        grid: { left: 12, right: 20, top: 58, bottom: 12, containLabel: true },
        legend: {
            top: 0,
            textStyle: { color: palette.axisLabel },
            itemWidth: 16,
        },
        xAxis: {
            type: 'category',
            data: rows.value.map((row) => row.label),
            axisLabel: { color: palette.axisLabel, fontSize: 10 },
        },
        yAxis: {
            type: 'value',
            name: unit,
            axisLabel: { color: palette.axisLabel },
            splitLine: {
                lineStyle: { color: palette.gridLine, type: 'dashed' },
            },
        },
        tooltip: {
            ...(dashboardChartPreset.tooltip as object),
            confine: true,
            valueFormatter: (value) =>
                `${number(value == null ? null : Number(value))} ${unit}`,
        },
        series,
    };
}
const flowOption = computed(() =>
    chart('ton', [
        {
            name: 'Produksi',
            type: 'bar',
            barMaxWidth: 24,
            itemStyle: { color: palette.production },
            data: rows.value.map((row) => row.production),
        },
        {
            name: 'Pemanfaatan',
            type: 'bar',
            barMaxWidth: 24,
            itemStyle: { color: palette.utilization },
            data: rows.value.map((row) => row.utilization),
        },
    ]),
);
const balanceOption = computed(() =>
    chart('ton', [
        {
            name: 'Saldo akhir',
            type: 'line',
            itemStyle: { color: palette.balance },
            data: rows.value.map((row) => row.capacity.balance),
        },
        {
            name: 'Kapasitas TPS',
            type: 'line',
            symbol: 'none',
            lineStyle: { type: 'dashed', color: palette.axisLabel },
            data: rows.value.map((row) =>
                row.capacity.available ? row.capacity.capacity : null,
            ),
        },
    ]),
);
const occupancyOption = computed(() =>
    chart('%', [
        {
            name: 'Okupansi TPS',
            type: 'bar',
            barMaxWidth: 24,
            data: rows.value.map((row) => ({
                value: row.capacity.utilization_percentage,
                itemStyle: {
                    color:
                        row.capacity.status === 'critical'
                            ? palette.critical
                            : row.capacity.status === 'warning'
                              ? palette.warning
                              : palette.balance,
                },
            })),
        },
        ...(['warning', 'critical'] as const).map((level) => ({
            name: level === 'warning' ? 'Ambang peringatan' : 'Ambang kritis',
            type: 'line' as const,
            symbol: 'none',
            lineStyle: { type: 'dashed' as const },
            itemStyle: { color: palette[level] },
            data: rows.value.map((row) =>
                row.capacity.available
                    ? row.capacity[`${level}_threshold`]
                    : null,
            ),
        })),
    ]),
);
</script>

<template>
    <Card
        class="min-w-0 gap-0 overflow-hidden rounded-2xl border border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
    >
        <CardHeader
            class="border-b border-slate-100 px-5 py-5 dark:border-slate-800"
        >
            <CardTitle class="wm-text-primary text-base"
                >Produksi, Pemanfaatan & Kapasitas FABA</CardTitle
            >
            <CardDescription
                >{{ period }} · Tren enam bulan · ton</CardDescription
            >
            <div class="flex flex-wrap gap-2 pt-2" aria-label="Material FABA">
                <button
                    v-for="material in materials"
                    :key="material.value"
                    type="button"
                    :aria-pressed="selectedMaterial === material.value"
                    class="min-h-11 rounded-xl border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    :class="
                        selectedMaterial === material.value
                            ? 'border-blue-700 bg-blue-700 text-white'
                            : 'wm-text-secondary wm-border-default'
                    "
                    @click="selectedMaterial = material.value"
                >
                    {{ material.label }}
                </button>
            </div>
        </CardHeader>
        <CardContent class="space-y-5 px-5 py-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="wm-surface-subtle rounded-xl border p-3">
                    <p class="wm-text-secondary text-xs">
                        Produksi · enam bulan
                    </p>
                    <p class="wm-text-primary mt-1 text-xl font-semibold">
                        {{ number(production) }} ton
                    </p>
                </div>
                <div class="wm-surface-subtle rounded-xl border p-3">
                    <p class="wm-text-secondary text-xs">
                        Pemanfaatan · enam bulan
                    </p>
                    <p class="wm-text-primary mt-1 text-xl font-semibold">
                        {{ number(utilization) }} ton
                    </p>
                </div>
                <div
                    class="wm-surface-subtle rounded-xl border p-3"
                    :class="statusClass"
                >
                    <p class="text-xs">
                        Okupansi TPS · {{ rows.at(-1)?.label }}
                    </p>
                    <p class="mt-1 text-xl font-semibold">
                        {{ number(latest?.utilization_percentage)
                        }}{{ latest?.available ? '%' : '' }}
                    </p>
                    <p class="text-xs font-semibold">{{ statusLabel }}</p>
                </div>
            </div>
            <div
                class="wm-surface-subtle space-y-1 rounded-xl border p-3 text-xs"
            >
                <p class="wm-text-primary">
                    Saldo akhir {{ number(latest?.balance) }} ton · Kapasitas
                    {{
                        latest?.available
                            ? number(latest.capacity) + ' ton'
                            : 'Tidak tersedia'
                    }}
                </p>
                <p v-if="latest?.available" :class="statusClass">
                    Terisi {{ number(latest.occupied_quantity) }} ton · Sisa
                    kapasitas {{ number(latest.remaining_capacity) }} ton ·
                    Kelebihan {{ number(latest.excess_quantity) }} ton
                </p>
                <p class="wm-text-secondary">
                    Peringatan kapasitas: {{ capacityWarnings }} dari
                    {{ rows.length }} bulan (mencapai ambang peringatan/kritis).
                    Integritas data seluruh material:
                    {{ integrityWarnings }} bulan.
                </p>
                <p class="wm-text-secondary">
                    Kapasitas dan ambang mengikuti konfigurasi TPS saat ini;
                    saldo mengikuti snapshot bulanan.
                </p>
            </div>
            <p
                v-if="
                    !rows.some(
                        (row) =>
                            row.production ||
                            row.utilization ||
                            row.capacity.balance,
                    )
                "
                class="wm-text-muted text-sm"
            >
                Belum ada aktivitas atau saldo FABA pada periode ini.
            </p>
            <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                <section class="wm-panel rounded-xl border p-3">
                    <h3 class="wm-text-primary mb-3 text-sm font-semibold">
                        Produksi vs Pemanfaatan
                    </h3>
                    <BaseEChart
                        :option="flowOption"
                        height-class="h-[280px] xl:h-[540px]"
                    />
                </section>
                <section class="wm-panel space-y-4 rounded-xl border p-3">
                    <h3 class="wm-text-primary text-sm font-semibold">
                        Saldo Akhir & Kapasitas TPS
                    </h3>
                    <div class="grid min-w-0 gap-4">
                        <BaseEChart
                            :option="balanceOption"
                            height-class="h-[260px]"
                        />
                        <BaseEChart
                            :option="occupancyOption"
                            height-class="h-[260px]"
                        />
                    </div>
                </section>
            </div>
            <div
                class="flex flex-wrap gap-4 text-sm font-semibold text-blue-700 dark:text-blue-300"
            >
                <Link :href="wasteManagementRoutes.faba.production.index.url()"
                    >Detail produksi</Link
                >
                <Link :href="wasteManagementRoutes.faba.utilization.index.url()"
                    >Detail pemanfaatan</Link
                >
                <Link :href="wasteManagementRoutes.faba.recaps.balance.url()"
                    >Saldo TPS</Link
                >
            </div>
            <Link
                :href="wasteManagementRoutes.faba.recaps.monthly.url()"
                class="text-sm font-semibold text-blue-700 dark:text-blue-300"
                >Lihat rekap FABA</Link
            >
        </CardContent>
    </Card>
</template>
