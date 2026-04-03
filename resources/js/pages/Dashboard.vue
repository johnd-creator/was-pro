<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

// New compact components
import CombinedApprovals from '@/components/dashboard/CombinedApprovals.vue';
import CompactStat from '@/components/dashboard/CompactStat.vue';
import ComplianceHero from '@/components/dashboard/ComplianceHero.vue';
import DistributionDonut from '@/components/dashboard/DistributionDonut.vue';
import FabaTrendChart from '@/components/dashboard/FabaTrendChart.vue';
import WasteFlowChart from '@/components/dashboard/WasteFlowChart.vue';
import Heading from '@/components/Heading.vue';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useReducedMotion } from '@/composables/useReducedMotion';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard as dashboardRoute } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboardRoute.url(),
    },
];

// Interfaces
interface Stats {
    waste_total_records_snapshot: number;
    waste_transported_records_snapshot: number;
    waste_untransported_records_snapshot: number;
    total_waste_records: number;
    approved_records: number;
    pending_records: number;
    total_transportations: number;
    in_transit_transportations: number;
    expired_waste: number;
    expiring_soon_waste: number;
}

interface FabaStats {
    total_production: number;
    total_utilization: number;
    current_balance: number;
    negative_periods: number;
}

interface ApprovalItem {
    id: string;
    record_number?: string;
    waste_type?: string;
    category?: string;
    quantity?: number;
    unit?: string;
    submitted_by?: string;
    submitted_at?: string;
    year?: number;
    month?: number;
    period_label?: string;
    status: string;
    type: 'waste_record' | 'faba_approval';
}

interface CategoryData {
    label: string;
    value: number;
    percentage: number;
}

interface Props {
    organizationName: string;
    header: {
        current_date: string;
        current_time: string;
        snapshot_month_label?: string;
        risk_status: 'normal' | 'warning' | 'critical';
        risk_label: string;
        risk_tone: 'green' | 'orange' | 'red';
    };
    filters: {
        month: string | null;
        organization_id: string | null;
    };
    availableMonths: Array<{
        value: string;
        label: string;
    }>;
    availableOrganizations: Array<{
        id: string;
        name: string;
        code: string;
    }>;
    stats: Stats;
    pendingApprovals: ApprovalItem[];
    wasteByCategory: CategoryData[];
    fabaProductionMaterialDistribution: CategoryData[];
    fabaStats: FabaStats;
    fabaChart: Array<{
        label: string;
        month: number;
        year: number;
        production: number;
        utilization: number;
        closing_balance: number;
    }>;
    fabaPendingApprovals: ApprovalItem[];
    fabaWarnings: Array<{
        month: number;
        period_label: string;
        message: string;
        closing_balance: number;
    }>;
    wasteChart: Array<{
        label: string;
        month: number;
        year: number;
        input_count: number;
        transported_count: number;
    }>;
}

const props = defineProps<Props>();

const wasteStatsContext = computed(() => {
    const snapshotLabel = props.header.snapshot_month_label ?? 'periode aktif';
    return {
        label: 's.d. Snapshot',
        totalHint: `Tercatat sampai ${snapshotLabel}`,
        transportedHint: `Sudah terangkut valid`,
        backlogHint: `Carry-over aktif`,
        fabaLabel: props.header.snapshot_month_label ?? 'Snapshot',
    };
});

// Computed properties
const combinedApprovals = computed(() => {
    const wasteApprovals = props.pendingApprovals.map((a) => ({
        ...a,
        type: 'waste_record' as const,
    }));
    const fabaApprovals = props.fabaPendingApprovals.map((a) => ({
        ...a,
        type: 'faba_approval' as const,
    }));

    return [...wasteApprovals, ...fabaApprovals];
});

const pageDescription = computed(() =>
    props.organizationName
        ? `Pantau risiko kepatuhan, operasional limbah, dan data FABA untuk ${props.organizationName} pada snapshot ${props.header.snapshot_month_label ?? 'periode aktif'}.`
        : 'Pantau risiko kepatuhan, operasional limbah, dan data FABA dalam satu tampilan terpadu.',
);

// Animation
const { prefersReducedMotion } = useReducedMotion();
const isMounted = ref(false);

onMounted(() => {
    const delay = prefersReducedMotion.value ? 0 : 100;
    setTimeout(() => {
        isMounted.value = true;
    }, delay);
});

const entranceAnimationClass = computed(() =>
    prefersReducedMotion.value ? '' : 'animate-fade-in',
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            :class="[
                'relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8',
                entranceAnimationClass,
            ]"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[420px] dark:from-slate-950 dark:via-slate-950"
            />
            <div
                class="pointer-events-none absolute -top-16 left-1/2 -z-10 h-64 w-64 -translate-x-[55%] rounded-full bg-blue-200/20 blur-3xl dark:bg-blue-500/10"
            />
            <div
                class="pointer-events-none absolute top-36 right-0 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl dark:bg-emerald-500/8"
            />

            <div class="space-y-8 lg:space-y-10">
                <div class="sr-only">
                    <Heading
                        title="Dashboard Terpadu"
                        :description="pageDescription"
                    />
                </div>

                <!-- Row 1: Compliance & Risk Hero -->
                <section>
                    <ComplianceHero
                        :expired-waste="stats.expired_waste"
                        :expiring-soon-waste="stats.expiring_soon_waste"
                        :pending-waste-approvals="pendingApprovals.length"
                        :pending-faba-approvals="fabaPendingApprovals.length"
                        :faba-warnings="fabaWarnings.length"
                        :risk-status="header.risk_status"
                        :risk-label="header.risk_label"
                        :risk-tone="header.risk_tone"
                    />
                </section>

                <!-- Row 2: Compact Stats Grid (6 columns) -->
                <section class="relative lg:-mt-3">
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Snapshot Board
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span
                                    class="h-px w-8 bg-slate-300 dark:bg-slate-700"
                                />
                                Ringkasan angka utama
                            </h3>
                        </div>
                        <p
                            class="hidden text-sm text-slate-500 lg:block dark:text-slate-400"
                        >
                            Enam KPI untuk membaca posisi operasional dalam satu
                            scan.
                        </p>
                    </div>

                    <div
                        class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                    >
                        <CompactStat
                            title="Total Limbah"
                            :value="stats.waste_total_records_snapshot"
                            :context-label="wasteStatsContext.label"
                            :hint="wasteStatsContext.totalHint"
                            color="blue"
                        />
                        <CompactStat
                            title="Limbah Terangkut"
                            :value="stats.waste_transported_records_snapshot"
                            :context-label="wasteStatsContext.label"
                            :hint="wasteStatsContext.transportedHint"
                            color="emerald"
                        />
                        <CompactStat
                            title="Belum Terangkut"
                            :value="stats.waste_untransported_records_snapshot"
                            :context-label="wasteStatsContext.label"
                            :hint="wasteStatsContext.backlogHint"
                            :color="
                                stats.waste_untransported_records_snapshot > 0
                                    ? 'orange'
                                    : 'blue'
                            "
                        />
                        <CompactStat
                            title="FABA Produksi"
                            :value="fabaStats.total_production"
                            unit="ton"
                            :context-label="wasteStatsContext.fabaLabel"
                            color="blue"
                        />
                        <CompactStat
                            title="FABA Pemanfaatan"
                            :value="fabaStats.total_utilization"
                            unit="ton"
                            :context-label="wasteStatsContext.fabaLabel"
                            color="emerald"
                        />
                        <CompactStat
                            title="Saldo FABA"
                            :value="fabaStats.current_balance"
                            unit="ton"
                            :context-label="wasteStatsContext.fabaLabel"
                            :color="
                                fabaStats.current_balance < 0
                                    ? 'red'
                                    : 'emerald'
                            "
                        />
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Trend Monitor
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Ritme operasional 6 bulan terakhir
                            </h3>
                        </div>
                        <p
                            class="hidden max-w-md text-right text-sm text-slate-500 lg:block dark:text-slate-400"
                        >
                            Bandingkan laju input, pengangkutan, produksi, dan
                            pemanfaatan tanpa tenggelam dalam caption panjang.
                        </p>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <WasteFlowChart :data="wasteChart" />
                        <FabaTrendChart :data="fabaChart" />
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Composition
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span
                                    class="h-px w-8 bg-slate-300 dark:bg-slate-700"
                                />
                                Komposisi kategori dan material
                            </h3>
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-blue-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-blue-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader class="space-y-2 pb-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase dark:text-blue-300/70"
                                >
                                    Waste Mix
                                </p>
                                <CardTitle class="text-lg dark:text-slate-100">
                                    Distribusi Kategori Limbah
                                </CardTitle>
                                <CardDescription
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Komposisi approved pada snapshot aktif.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <DistributionDonut
                                    :data="wasteByCategory"
                                    total-label="Total"
                                    value-suffix="kg"
                                />
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-emerald-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase dark:text-emerald-300/70"
                                >
                                    FABA Mix
                                </p>
                                <CardTitle class="text-lg dark:text-slate-100">
                                    Distribusi Material Produksi FABA
                                </CardTitle>
                                <CardDescription
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Komposisi material produksi pada snapshot
                                    aktif.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <DistributionDonut
                                    :data="fabaProductionMaterialDistribution"
                                    total-label="Total"
                                    value-suffix="ton"
                                />
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
                                Workflow
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Antrian keputusan operasional
                            </h3>
                        </div>
                    </div>

                    <CombinedApprovals :approvals="combinedApprovals" />
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}
</style>
