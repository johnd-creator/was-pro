<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
} from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import FabaHeroKpiCards from '@/components/dashboard/FabaHeroKpiCards.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaCapacitySummary,
    FabaPendingTransactionApprovalItem,
    FabaVerificationMovementItem,
    FabaVerificationQueueItem,
} from '@/types/faba';

const props = defineProps<{
    year: number;
    stats: {
        total_production: number;
        total_utilization: number;
        current_balance: number;
        negative_periods: number;
    };
    trend: Array<{
        month: number;
        label: string;
        production: number;
        utilization: number;
        closing_balance: number;
    }>;
    pendingApprovals: Array<{
        id: string;
        year: number;
        month: number;
        period_label: string;
        status: string;
    }>;
    readyClosingPeriods: FabaVerificationQueueItem[];
    warnings: Array<{
        month: number;
        period_label: string;
        message: string;
        closing_balance: number;
    }>;
    latestApprovedPeriod?: {
        id: string;
        year: number;
        month: number;
        status: string;
        period_label: string;
    } | null;
    pendingTransactionApprovals: FabaPendingTransactionApprovalItem[];
    latestMovements: FabaVerificationMovementItem[];
    topVendors: Array<{
        vendor_id: string | null;
        vendor_name: string;
        total_quantity: number;
    }>;
    tpsCapacitySummary: FabaCapacitySummary;
}>();

const filterForm = reactive({
    year: props.year,
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Dashboard FABA',
        href: wasteManagementRoutes.faba.dashboard.url(),
    },
];

const latestPeriodTone = computed(() => {
    if (!props.latestApprovedPeriod) {
        return 'border-slate-200/80 bg-white/90 text-slate-600 dark:border-slate-800/80 dark:bg-slate-950/85 dark:text-slate-300';
    }

    return 'border-blue-200/70 bg-blue-50/85 text-blue-700 dark:border-blue-900/70 dark:bg-blue-950/35 dark:text-blue-200';
});

const dashboardHealthLabel = computed(() => {
    if (props.warnings.length > 0 || props.stats.negative_periods > 0) {
        return 'Butuh tindak lanjut';
    }

    if (props.readyClosingPeriods.length > 0) {
        return 'Siap closing';
    }

    if (props.pendingApprovals.length > 0) {
        return 'Review operasional';
    }

    return 'Terkendali';
});

const dashboardHealthCount = computed(
    () =>
        props.warnings.length +
        props.pendingApprovals.length +
        props.pendingTransactionApprovals.length +
        props.readyClosingPeriods.length +
        props.stats.negative_periods,
);

const latestOperationalMovements = computed(() =>
    props.latestMovements.slice(0, 6),
);

const strongestTrendMonth = computed(
    () =>
        [...props.trend].sort(
            (left, right) => right.production - left.production,
        )[0] ?? null,
);

const trendRows = computed(() =>
    props.trend.slice(-6).map((item) => ({
        ...item,
        gap: item.production - item.utilization,
    })),
);

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.dashboard(), filterForm);
}

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID');
}

function formatPercentage(value: number): string {
    return `${value.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })}%`;
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Dashboard FABA"
    >
        <Head title="Dashboard FABA" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[360px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-blue-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-32 right-0 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />

            <div class="space-y-8 lg:space-y-10">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-blue-50/20 dark:via-slate-900 dark:to-blue-950/20"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase"
                                >
                                    Pusat Kendali FABA
                                </p>
                                <Heading
                                    title="Dashboard FABA"
                                    description="Pantau produksi, pemanfaatan, saldo, dan review periode bulanan dari satu kanvas operasional."
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Tahun aktif: {{ year }}
                                </div>
                                <div
                                    class="wm-chip-blue px-3 py-1.5 text-xs font-medium"
                                >
                                    {{ dashboardHealthLabel }}:
                                    {{ dashboardHealthCount }}
                                </div>
                                <div
                                    class="wm-chip-emerald px-3 py-1.5 text-xs font-medium"
                                >
                                    Vendor aktif: {{ topVendors.length }}
                                </div>
                            </div>

                            <div
                                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <FabaHeroKpiCards
                                    :year="year"
                                    :stats="stats"
                                    variant="full"
                                />
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-4 rounded-[28px] p-5"
                        >
                            <div class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Filter Snapshot
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Pilih horizon review
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label>Tahun</Label>
                                <Input
                                    v-model="filterForm.year"
                                    type="number"
                                    class="h-11 bg-white dark:bg-slate-950"
                                />
                            </div>

                            <Button class="w-full" @click="applyFilters">
                                Terapkan Tahun
                            </Button>

                            <div
                                :class="[
                                    'rounded-[22px] border p-4',
                                    latestPeriodTone,
                                ]"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] uppercase"
                                >
                                    Periode approved terakhir
                                </p>
                                <div
                                    v-if="latestApprovedPeriod"
                                    class="mt-3 space-y-3"
                                >
                                    <p
                                        class="text-lg font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ latestApprovedPeriod.period_label }}
                                    </p>
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full border border-blue-200 bg-white/90 text-blue-700 dark:bg-slate-950/85"
                                    >
                                        {{
                                            formatFabaStatus(
                                                latestApprovedPeriod.status,
                                            )
                                        }}
                                    </Badge>
                                    <Link
                                        class="inline-flex items-center gap-2 text-sm font-medium text-blue-700 transition-colors hover:text-blue-900"
                                        :href="
                                            wasteManagementRoutes.faba.approvals.review(
                                                [
                                                    latestApprovedPeriod.year,
                                                    latestApprovedPeriod.month,
                                                ],
                                            ).url
                                        "
                                    >
                                        Lihat review periode
                                        <ArrowRight class="size-4" />
                                    </Link>
                                </div>
                                <p
                                    v-else
                                    class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada periode yang disetujui.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Kapasitas TPS
                            </p>
                            <h3
                                class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                Monitoring utilisasi penyimpanan
                            </h3>
                        </div>
                        <Badge
                            variant="secondary"
                            class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700"
                        >
                            {{ tpsCapacitySummary.period.period_label }}
                        </Badge>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card
                            class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85 lg:col-span-2"
                        >
                            <CardHeader>
                                <CardTitle>Utilisasi per material</CardTitle>
                            </CardHeader>
                            <CardContent class="grid gap-3 md:grid-cols-2">
                                <div
                                    v-for="item in tpsCapacitySummary.materials"
                                    :key="item.material_type"
                                    class="wm-surface-subtle rounded-[22px] p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950 dark:text-slate-100">
                                                {{ formatFabaMaterial(item.material_type) }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ formatNumber(item.balance) }} / {{ formatNumber(item.capacity) }} ton
                                            </p>
                                        </div>
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full"
                                            :class="{
                                                'border border-emerald-200 bg-emerald-50 text-emerald-700': item.status === 'normal',
                                                'border border-amber-200 bg-amber-50 text-amber-700': item.status === 'warning',
                                                'border border-rose-200 bg-rose-50 text-rose-700': item.status === 'critical',
                                            }"
                                        >
                                            {{ formatPercentage(item.utilization_percentage) }}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <CardHeader>
                                <CardTitle>Utilisasi Total</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <p class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                        {{ formatPercentage(tpsCapacitySummary.total.utilization_percentage) }}
                                    </p>
                                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                        {{ formatNumber(tpsCapacitySummary.total.balance) }} dari {{ formatNumber(tpsCapacitySummary.total.capacity) }} ton kapasitas.
                                    </p>
                                </div>
                                <div class="wm-surface-subtle rounded-[20px] p-4 text-sm text-slate-600 dark:text-slate-300">
                                    Threshold warning {{ formatPercentage(tpsCapacitySummary.thresholds.warning) }}
                                    dan critical {{ formatPercentage(tpsCapacitySummary.thresholds.critical) }}.
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Monitor Tren
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Ritme produksi dan serapan
                            </h3>
                        </div>
                        <p
                            class="hidden max-w-md text-right text-sm text-slate-500 lg:block dark:text-slate-400"
                        >
                            Fokus pada 6 periode terakhir untuk membaca gap
                            antara suplai dan pemanfaatan.
                        </p>
                    </div>

                    <div
                        class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]"
                    >
                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-blue-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-blue-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader class="space-y-4 pb-4">
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase"
                                        >
                                            Alur 6 Bulan
                                        </p>
                                        <CardTitle
                                            class="mt-2 text-lg tracking-tight sm:text-xl"
                                        >
                                            Produksi vs Pemanfaatan
                                        </CardTitle>
                                    </div>
                                    <div
                                        class="rounded-[24px] border border-white/80 bg-white/85 px-4 py-3 text-right shadow-sm shadow-slate-200/50 dark:bg-slate-950/80"
                                    >
                                        <p
                                            class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Bulan terkuat
                                        </p>
                                        <p
                                            class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                strongestTrendMonth?.label ??
                                                '-'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-0">
                                <div
                                    v-if="trendRows.length === 0"
                                    class="rounded-[24px] border border-dashed border-slate-200 bg-white/80 px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada tren produksi dan pemanfaatan pada
                                    tahun ini.
                                </div>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="item in trendRows"
                                        :key="item.month"
                                        class="grid gap-3 rounded-[24px] border border-white/90 bg-white/85 px-4 py-4 shadow-sm shadow-slate-100/70 md:grid-cols-[120px_minmax(0,1fr)_auto] dark:bg-slate-950/80"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                            >
                                                {{ item.label }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Saldo
                                                {{
                                                    formatNumber(
                                                        item.closing_balance,
                                                    )
                                                }}
                                                ton
                                            </p>
                                        </div>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <div
                                                class="rounded-[18px] border border-blue-200/70 bg-blue-50/75 px-3 py-2"
                                            >
                                                <p
                                                    class="text-[11px] font-semibold tracking-wide text-blue-700 uppercase"
                                                >
                                                    Produksi
                                                </p>
                                                <p
                                                    class="mt-1 text-base font-semibold text-blue-900"
                                                >
                                                    {{
                                                        formatNumber(
                                                            item.production,
                                                        )
                                                    }}
                                                    ton
                                                </p>
                                            </div>
                                            <div
                                                class="rounded-[18px] border border-emerald-200/70 bg-emerald-50/75 px-3 py-2"
                                            >
                                                <p
                                                    class="text-[11px] font-semibold tracking-wide text-emerald-700 uppercase"
                                                >
                                                    Pemanfaatan
                                                </p>
                                                <p
                                                    class="mt-1 text-base font-semibold text-emerald-900"
                                                >
                                                    {{
                                                        formatNumber(
                                                            item.utilization,
                                                        )
                                                    }}
                                                    ton
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-right dark:bg-slate-900/70"
                                        >
                                            <p
                                                class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Gap
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100"
                                            >
                                                {{ formatNumber(item.gap) }} ton
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="space-y-6">
                            <Card
                                class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-sky-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-sky-950/18 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                            >
                                <CardHeader class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-sky-700/70 uppercase"
                                    >
                                        Approval Harian
                                    </p>
                                    <CardTitle class="text-lg tracking-tight">
                                        Transaksi menunggu persetujuan
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div
                                        v-if="pendingTransactionApprovals.length === 0"
                                        class="rounded-[22px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Tidak ada transaksi harian yang menunggu persetujuan.
                                    </div>
                                    <div v-else class="space-y-3">
                                        <Link
                                            v-for="item in pendingTransactionApprovals"
                                            :key="item.id"
                                            :href="
                                                item.movement_type.startsWith('adjustment')
                                                    ? wasteManagementRoutes.faba.adjustments.show(item.id).url
                                                    : item.movement_type.startsWith('utilization')
                                                      ? wasteManagementRoutes.faba.utilization.show(item.id).url
                                                      : wasteManagementRoutes.faba.production.show(item.id).url
                                            "
                                            class="group block rounded-[22px] border border-white/90 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/70 transition-all hover:-translate-y-0.5 hover:border-sky-200 hover:bg-white dark:bg-slate-950 dark:bg-slate-950/85"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-semibold text-slate-950 dark:text-slate-100">
                                                        {{ item.display_number }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ formatFabaMovementType(item.movement_type) }} •
                                                        {{ formatFabaMaterial(item.material_type) }} •
                                                        {{ item.period_label }}
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-100">
                                                        {{ formatNumber(item.quantity) }} {{ item.unit }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ item.created_by_user?.name ?? 'Sistem' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card
                                class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-emerald-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950/18 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                            >
                                <CardHeader class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                                    >
                                        Readiness Closing
                                    </p>
                                    <CardTitle class="text-lg tracking-tight">
                                        Periode siap diajukan
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div
                                        v-if="readyClosingPeriods.length === 0"
                                        class="rounded-[22px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Belum ada periode yang siap diajukan untuk closing.
                                    </div>
                                    <div v-else class="space-y-3">
                                        <Link
                                            v-for="item in readyClosingPeriods"
                                            :key="`${item.year}-${item.month}`"
                                            :href="
                                                wasteManagementRoutes.faba.approvals.index.url({
                                                    query: { year: item.year },
                                                })
                                            "
                                            class="group block rounded-[22px] border border-white/90 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/70 transition-all hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-white dark:bg-slate-950 dark:bg-slate-950/85"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-3"
                                            >
                                                <div>
                                                    <p
                                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                                    >
                                                        {{ item.period_label }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        Saldo akhir {{ formatNumber(item.closing_balance) }} ton
                                                        dengan {{ item.warning_count }} warning aktif.
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant="secondary"
                                                    class="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700"
                                                >
                                                    Siap submit
                                                </Badge>
                                            </div>
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card
                                class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-orange-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-orange-950/18 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                            >
                                <CardHeader class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-orange-700/70 uppercase"
                                    >
                                        Antrian Persetujuan
                                    </p>
                                    <CardTitle class="text-lg tracking-tight">
                                        Periode menunggu review
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div
                                        v-if="pendingApprovals.length === 0"
                                        class="rounded-[22px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Tidak ada approval yang menunggu review.
                                    </div>
                                    <div v-else class="space-y-3">
                                        <Link
                                            v-for="item in pendingApprovals"
                                            :key="item.id"
                                            :href="
                                                wasteManagementRoutes.faba.approvals.review(
                                                    [item.year, item.month],
                                                ).url
                                            "
                                            class="group block rounded-[22px] border border-white/90 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/70 transition-all hover:-translate-y-0.5 hover:border-orange-200 hover:bg-white dark:bg-slate-950 dark:bg-slate-950/85"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-3"
                                            >
                                                <div>
                                                    <p
                                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                                    >
                                                        {{ item.period_label }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        Menunggu keputusan
                                                        final.
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant="secondary"
                                                    class="rounded-full border border-orange-200 bg-orange-50 text-orange-700"
                                                >
                                                    {{
                                                        formatFabaStatus(
                                                            item.status,
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card
                                class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-rose-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-rose-950/18 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                            >
                                <CardHeader class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-rose-700/70 uppercase"
                                    >
                                        Catatan Risiko
                                    </p>
                                    <CardTitle class="text-lg tracking-tight">
                                        Warning saldo dan konsistensi
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div
                                        v-if="warnings.length === 0"
                                        class="rounded-[22px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Tidak ada anomali saldo pada periode
                                        berjalan.
                                    </div>
                                    <div v-else class="space-y-3">
                                        <div
                                            v-for="item in warnings"
                                            :key="`${item.month}-${item.message}`"
                                            class="rounded-[22px] border border-rose-200/70 bg-white/90 px-4 py-3 shadow-sm shadow-rose-100/50 dark:bg-slate-950/85"
                                        >
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="rounded-2xl bg-rose-100 p-2 text-rose-700"
                                                >
                                                    <AlertTriangle
                                                        class="size-4"
                                                    />
                                                </div>
                                                <div class="space-y-1">
                                                    <p
                                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                                    >
                                                        {{ item.period_label }}
                                                    </p>
                                                    <p
                                                        class="text-sm text-slate-600 dark:text-slate-300"
                                                    >
                                                        {{ item.message }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Verification Feed
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Transaksi terbaru untuk supervisor
                            </h3>
                        </div>
                    </div>

                    <Card
                        class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-sky-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-sky-950/18 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                    >
                        <CardContent class="p-5">
                            <div
                                v-if="latestOperationalMovements.length === 0"
                                class="rounded-[24px] border border-dashed border-slate-200 bg-white/80 px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400"
                            >
                                Belum ada transaksi FABA pada periode yang tersedia.
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in latestOperationalMovements"
                                    :key="item.id"
                                    class="rounded-[22px] border border-white/90 bg-white/90 px-4 py-4 shadow-sm shadow-slate-100/70 dark:bg-slate-950/85"
                                >
                                    <div
                                        class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                                    >
                                        <div class="space-y-1">
                                            <p
                                                class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                            >
                                                {{ item.display_number }}
                                            </p>
                                            <p
                                                class="text-sm text-slate-600 dark:text-slate-300"
                                            >
                                                {{
                                                    formatFabaMovementType(
                                                        item.movement_type,
                                                    )
                                                }}
                                                •
                                                {{
                                                    formatFabaMaterial(
                                                        item.material_type,
                                                    )
                                                }}
                                                • {{ item.period_label }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{ item.transaction_date }} •
                                                {{ item.created_by_user?.name ?? 'Sistem' }}
                                            </p>
                                        </div>
                                        <div class="text-left lg:text-right">
                                            <p
                                                class="text-base font-semibold text-slate-950 dark:text-slate-100"
                                            >
                                                {{ formatNumber(item.quantity) }} {{ item.unit }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    item.vendor_name ??
                                                    item.internal_destination_name ??
                                                    item.purpose_name ??
                                                    'Tanpa tujuan tambahan'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Sorotan Vendor
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Kontributor pemanfaatan eksternal
                            </h3>
                        </div>
                    </div>

                    <Card
                        class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-emerald-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                    >
                        <CardContent class="p-5">
                            <div
                                v-if="topVendors.length === 0"
                                class="rounded-[24px] border border-dashed border-slate-200 bg-white/80 px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400"
                            >
                                Belum ada data vendor eksternal pada tahun ini.
                            </div>
                            <div
                                v-else
                                class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                            >
                                <div
                                    v-for="item in topVendors"
                                    :key="item.vendor_id ?? item.vendor_name"
                                    class="rounded-[22px] border border-white/90 bg-white/90 px-4 py-4 shadow-sm shadow-slate-100/70 dark:bg-slate-950/85"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                            >
                                                {{ item.vendor_name }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Total serapan eksternal
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-full border border-emerald-200/80 bg-emerald-50/90 px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] text-emerald-700 uppercase"
                                        >
                                            Vendor
                                        </div>
                                    </div>
                                    <p
                                        class="mt-4 text-2xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.total_quantity) }}
                                        <span
                                            class="ml-1 text-sm font-medium text-slate-400"
                                            >ton</span
                                        >
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
