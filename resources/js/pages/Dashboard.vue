<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

// New compact components
import CategoryChart from '@/components/dashboard/CategoryChart.vue';
import CombinedApprovals from '@/components/dashboard/CombinedApprovals.vue';
import CompactStat from '@/components/dashboard/CompactStat.vue';
import ComplianceHero from '@/components/dashboard/ComplianceHero.vue';
import FabaTrendChart from '@/components/dashboard/FabaTrendChart.vue';
import MiniChart from '@/components/dashboard/MiniChart.vue';
import StatusDistribution from '@/components/dashboard/StatusDistribution.vue';
import WasteTrendChart from '@/components/dashboard/WasteTrendChart.vue';
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
    category: string;
    count: number;
    percentage: number;
}

interface StatusData {
    status: string;
    count: number;
    color: string;
    percentage?: number;
}

interface Props {
    stats: Stats;
    pendingApprovals: ApprovalItem[];
    wasteByCategory: CategoryData[];
    transportationByStatus: StatusData[];
    fabaStats: FabaStats;
    fabaChart: Array<{
        label: string;
        month: number;
        year: number;
        production: number;
        utilization: number;
        closing_balance: number;
    }>;
    wasteChart: Array<{
        label: string;
        month: number;
        year: number;
        records_count: number;
        approved_count: number;
        transport_delivered_count: number;
    }>;
    fabaPendingApprovals: ApprovalItem[];
    fabaWarnings: Array<{
        month: number;
        period_label: string;
        message: string;
        closing_balance: number;
    }>;
    latestFabaPeriod?: {
        id: string;
        year: number;
        month: number;
        status: string;
        period_label: string;
    } | null;
    topVendors: Array<{
        vendor_id: string | null;
        vendor_name: string;
        total_quantity: number;
    }>;
}

const props = defineProps<Props>();

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

const vendorData = computed(() =>
    props.topVendors.map((v) => ({
        label: v.vendor_name,
        value: v.total_quantity,
    })),
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
        <div :class="['space-y-6 p-6 lg:p-8', entranceAnimationClass]">
            <!-- Page Header -->
            <Heading
                title="Dashboard Terpadu"
                description="Pantau risiko kepatuhan, operasional limbah, dan data FABA dalam satu tampilan terpadu."
            />

            <!-- Row 1: Compliance & Risk Hero -->
            <section>
                <ComplianceHero
                    :expired-waste="stats.expired_waste"
                    :expiring-soon-waste="stats.expiring_soon_waste"
                    :pending-waste-approvals="pendingApprovals.length"
                    :pending-faba-approvals="fabaPendingApprovals.length"
                    :faba-warnings="fabaWarnings.length"
                />
            </section>

            <!-- Row 2: Compact Stats Grid (6 columns) -->
            <section>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <CompactStat
                        title="Total Limbah"
                        :value="stats.total_waste_records"
                        color="blue"
                    />
                    <CompactStat
                        title="Disetujui"
                        :value="stats.approved_records"
                        color="emerald"
                    />
                    <CompactStat
                        title="Pending"
                        :value="stats.pending_records"
                        :color="stats.pending_records > 0 ? 'orange' : 'blue'"
                    />
                    <CompactStat
                        title="FABA Produksi"
                        :value="fabaStats.total_production"
                        unit="ton"
                        color="blue"
                    />
                    <CompactStat
                        title="FABA Pemanfaatan"
                        :value="fabaStats.total_utilization"
                        unit="ton"
                        color="emerald"
                    />
                    <CompactStat
                        title="Saldo FABA"
                        :value="fabaStats.current_balance"
                        unit="ton"
                        :color="fabaStats.current_balance < 0 ? 'red' : 'emerald'"
                    />
                </div>
            </section>

            <!-- Row 3: Trend Charts (2 columns) -->
            <section class="grid gap-6 lg:grid-cols-2">
                <!-- FABA Trend Chart -->
                <FabaTrendChart :data="fabaChart" />

                <!-- Waste Trend Chart -->
                <WasteTrendChart :data="wasteChart" />
            </section>

            <!-- Row 4: Approvals & Analytics (2 columns) -->
            <section class="grid gap-6 lg:grid-cols-2">
                <!-- Left: Combined Approvals -->
                <CombinedApprovals :approvals="combinedApprovals" />

                <!-- Right: Secondary Analytics -->
                <div class="space-y-6">
                    <MiniChart title="Top Vendor FABA" :data="vendorData" />

                    <div class="grid gap-6 xl:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-base">
                                    Distribusi Kategori Limbah
                                </CardTitle>
                                <CardDescription>
                                    Komposisi kategori limbah yang paling dominan
                                    untuk membantu prioritas operasional.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CategoryChart :data="wasteByCategory" />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle class="text-base">
                                    Status Transportasi
                                </CardTitle>
                                <CardDescription>
                                    Ringkasan pengiriman untuk memantau progres
                                    dari penjadwalan hingga pengantaran.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <StatusDistribution
                                    :data="transportationByStatus"
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
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
