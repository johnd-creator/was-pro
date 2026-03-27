<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

// New compact components
import CategoryChart from '@/components/dashboard/CategoryChart.vue';
import CombinedApprovals from '@/components/dashboard/CombinedApprovals.vue';
import CompactStat from '@/components/dashboard/CompactStat.vue';
import ComplianceHero from '@/components/dashboard/ComplianceHero.vue';
import FabaTrendChart from '@/components/dashboard/FabaTrendChart.vue';
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
    organizationName: string;
    stats: Stats;
    pendingApprovals: ApprovalItem[];
    wasteByCategory: CategoryData[];
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

const pageDescription = computed(() =>
    props.organizationName
        ? `Pantau risiko kepatuhan, operasional limbah, dan data FABA untuk ${props.organizationName} dalam satu tampilan terpadu.`
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
            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)]">
                <FabaTrendChart :data="fabaChart" />

                <Card class="border-slate-200/80 shadow-sm">
                    <CardHeader class="space-y-2">
                        <CardTitle class="text-lg">
                            Distribusi Kategori Limbah
                        </CardTitle>
                        <CardDescription>
                            Komposisi kategori limbah yang paling dominan untuk
                            membantu prioritas operasional saat ini.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CategoryChart :data="wasteByCategory" />
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
