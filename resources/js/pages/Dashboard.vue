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

interface StatusData {
    status: string;
    count: number;
    color: string;
    percentage?: number;
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
    fabaWarnings: Array<{ month: number; period_label: string; message: string; closing_balance: number }>;
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
    const snapshotDate = props.filters.month ? new Date(`${props.filters.month}-01`) : null;
    const yearLabel = snapshotDate?.getFullYear() ?? new Date().getFullYear();

    return {
        label: 's.d. Snapshot',
        totalHint: `Seluruh catatan limbah yang sudah tercatat sampai ${snapshotLabel}.`,
        transportedHint: `Catatan yang sudah memiliki pengangkutan valid sampai ${snapshotLabel}.`,
        backlogHint: `Catatan approved yang masih punya sisa pengangkutan pada ${snapshotLabel}, termasuk carry-over tahun sebelumnya.`,
        yearHint: `Tahun ${yearLabel}`,
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
        <div :class="['space-y-8 p-6 lg:p-8', entranceAnimationClass]">
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
            <section>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
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
                        :color="stats.waste_untransported_records_snapshot > 0 ? 'orange' : 'blue'"
                    />
                    <CompactStat
                        title="FABA Produksi"
                        :value="fabaStats.total_production"
                        unit="ton"
                        :context-label="props.header.snapshot_month_label ?? wasteStatsContext.yearHint"
                        color="blue"
                    />
                    <CompactStat
                        title="FABA Pemanfaatan"
                        :value="fabaStats.total_utilization"
                        unit="ton"
                        :context-label="props.header.snapshot_month_label ?? wasteStatsContext.yearHint"
                        color="emerald"
                    />
                    <CompactStat
                        title="Saldo FABA"
                        :value="fabaStats.current_balance"
                        unit="ton"
                        :context-label="props.header.snapshot_month_label ?? wasteStatsContext.yearHint"
                        :color="fabaStats.current_balance < 0 ? 'red' : 'emerald'"
                    />
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <WasteFlowChart :data="wasteChart" />
                <FabaTrendChart :data="fabaChart" />
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <Card class="border-slate-200/80 shadow-sm">
                    <CardHeader class="space-y-2">
                        <CardTitle class="text-lg">
                            Distribusi Kategori Limbah
                        </CardTitle>
                        <CardDescription>
                            Komposisi volume limbah approved per kategori pada
                            snapshot bulan aktif.
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

                <Card class="border-slate-200/80 shadow-sm">
                    <CardHeader class="space-y-2">
                        <CardTitle class="text-lg">
                            Distribusi Material Produksi FABA
                        </CardTitle>
                        <CardDescription>
                            Komposisi material produksi Fly Ash dan Bottom Ash
                            pada tahun aktif dashboard.
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
            </section>

            <section>
                <CombinedApprovals :approvals="combinedApprovals" />
            </section>
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
