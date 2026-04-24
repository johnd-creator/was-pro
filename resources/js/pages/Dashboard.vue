<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import CompactStat from '@/components/dashboard/CompactStat.vue';
import ComplianceHero from '@/components/dashboard/ComplianceHero.vue';
import DistributionDonut from '@/components/dashboard/DistributionDonut.vue';
import FabaHeroKpiCards from '@/components/dashboard/FabaHeroKpiCards.vue';
import FabaTrendChart from '@/components/dashboard/FabaTrendChart.vue';
import TaskListPanel from '@/components/dashboard/TaskListPanel.vue';
import WasteFlowChart from '@/components/dashboard/WasteFlowChart.vue';

// UI components
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    expired_waste: number;
    expiring_soon_waste: number;
}

interface FabaStats {
    total_production: number;
    total_utilization: number;
    current_balance: number;
    negative_periods: number;
}

interface CategoryData {
    label: string;
    value: number;
    percentage: number;
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

interface DashboardTaskItem {
    id: string;
    type: 'waste_record' | 'faba_approval' | 'waste_hauling';
    task_group: 'approval' | 'revision' | 'follow_up';
    title: string;
    subtitle: string;
    status: string;
    priority: 'danger' | 'warning' | 'success' | 'info' | 'neutral';
    age_label: string;
    href: string;
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
    wasteHaulingStatusDistribution: CategoryData[];
    wasteBacklogUrgencyDistribution: CategoryData[];
    fabaStats: FabaStats;
    fabaHeroStats: FabaStats & {
        year: number;
    };
    fabaChart: Array<{
        label: string;
        month: number;
        year: number;
        production: number;
        utilization: number;
        closing_balance: number;
        production_fly_ash: number;
        production_bottom_ash: number;
        utilization_fly_ash: number;
        utilization_bottom_ash: number;
        closing_fly_ash: number;
        closing_bottom_ash: number;
        capacity_utilization_percentage: number;
        capacity_status: 'normal' | 'warning' | 'critical';
        capacity_warning_threshold: number;
        capacity_critical_threshold: number;
        warning_count: number;
        has_warning: boolean;
    }>;
    fabaMaterialBalanceDistribution: CategoryData[];
    fabaUtilizationDistribution: CategoryData[];
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
        approved_input_count: number;
        completed_count: number;
        closing_backlog_count: number;
        approved_input_quantity: number;
        hauled_quantity: number;
        closing_backlog_quantity: number;
        expired_backlog_quantity: number;
        expiring_soon_backlog_quantity: number;
        unit: string;
        other_units_count: number;
    }>;
    tasks: DashboardTaskItem[];
    taskContext: 'operator' | 'approver';
    wasteTasks: DashboardTaskItem[];
    fabaTasks: DashboardTaskItem[];
    haulingAttentionTasks: DashboardTaskItem[];
    wastePendingCount: number;
    fabaPendingCount: number;
    haulingAttentionCount: number;
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
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative overflow-x-hidden px-4 py-4 sm:px-6 sm:py-5 lg:px-8 lg:py-6"
        >
            <div class="space-y-4 lg:space-y-5">
                <section
                    class="grid gap-4 xl:grid-cols-6 xl:grid-rows-[auto_auto] xl:items-stretch"
                >
                    <div class="xl:col-span-4 xl:row-start-1">
                        <ComplianceHero
                            :expired-waste="stats.expired_waste"
                            :expiring-soon-waste="stats.expiring_soon_waste"
                            :pending-waste-approvals="pendingApprovals.length"
                            :pending-faba-approvals="
                                fabaPendingApprovals.length
                            "
                            :faba-warnings="fabaWarnings.length"
                            :risk-status="header.risk_status"
                            :risk-label="header.risk_label"
                            :risk-tone="header.risk_tone"
                            :show-metric-cards="false"
                        />
                    </div>

                    <div class="space-y-3 xl:col-span-4 xl:row-start-2">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p
                                    class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase"
                                >
                                    KPI Snapshot
                                </p>
                                <h3
                                    class="wm-text-primary mt-1 text-base font-semibold tracking-tight"
                                >
                                    Ringkasan Angka
                                </h3>
                            </div>
                            <p
                                class="wm-text-secondary hidden max-w-sm text-right text-sm lg:block"
                            >
                                Enam indikator utama untuk membaca posisi
                                operasional harian.
                            </p>
                        </div>

                        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                            <CompactStat
                                title="Total Catatan Limbah"
                                :value="stats.waste_total_records_snapshot"
                                :context-label="wasteStatsContext.label"
                                :hint="wasteStatsContext.totalHint"
                                color="blue"
                            />
                            <CompactStat
                                title="Menunggu Ditinjau"
                                :value="stats.pending_records"
                                context-label="Periode aktif"
                                hint="Butuh review supervisor"
                                :color="
                                    stats.pending_records > 0
                                        ? 'orange'
                                        : 'blue'
                                "
                            />
                            <CompactStat
                                title="Melewati Batas Simpan"
                                :value="stats.expired_waste"
                                context-label="Kepatuhan"
                                hint="Perlu penanganan segera"
                                :color="
                                    stats.expired_waste > 0 ? 'red' : 'emerald'
                                "
                            />
                            <FabaHeroKpiCards
                                :year="fabaHeroStats.year"
                                :stats="fabaHeroStats"
                                variant="summary"
                            />
                        </div>
                    </div>

                    <div
                        class="overflow-hidden xl:col-span-2 xl:row-span-2 xl:row-start-1 xl:min-h-0"
                    >
                        <TaskListPanel
                            :waste-tasks="wasteTasks"
                            :faba-tasks="fabaTasks"
                            :hauling-attention-tasks="haulingAttentionTasks"
                            :task-context="taskContext"
                            :waste-pending-count="wastePendingCount"
                            :faba-pending-count="fabaPendingCount"
                            :hauling-attention-count="haulingAttentionCount"
                        />
                    </div>
                </section>

                <section class="space-y-3 lg:space-y-4">
                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,1fr)]">
                        <div>
                            <p
                                class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Trend Monitor
                            </p>
                            <h3
                                class="wm-text-primary mt-1 text-base font-semibold tracking-tight"
                            >
                                Trend 6 Bulan
                            </h3>
                            <p class="wm-text-secondary mt-2 text-sm">
                                Pantau intake limbah approved, backlog
                                pengangkutan, produksi FABA, dan warning
                                periodenya dari satu area baca.
                            </p>
                        </div>
                        <div class="xl:justify-self-start">
                            <p
                                class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Composition
                            </p>
                            <h3
                                class="wm-text-primary mt-1 text-base font-semibold tracking-tight"
                            >
                                Donut Distribusi
                            </h3>
                        </div>
                    </div>

                    <div
                        class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,1fr)]"
                    >
                        <div class="space-y-6">
                            <WasteFlowChart :data="wasteChart" />
                            <FabaTrendChart :data="fabaChart" />
                        </div>

                        <div class="space-y-4">
                            <div class="grid gap-4 grid-cols-1">
                                <Card
                                    class="wm-panel-elevated wm-card-overlay-blue overflow-hidden rounded-2xl border shadow-sm dark:border-slate-700/50 dark:bg-slate-900/40"
                                >
                                    <CardHeader
                                        class="wm-border-default border-b bg-slate-50/40 pb-3 dark:bg-slate-900/60 dark:border-slate-700/50"
                                    >
                                        <CardTitle class="wm-text-primary text-base">
                                            Distribusi Status Pengangkutan Limbah
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <DistributionDonut
                                            :data="wasteHaulingStatusDistribution"
                                            total-label="Catatan"
                                            value-suffix="catatan"
                                        />
                                    </CardContent>
                                </Card>

                                <Card
                                    class="wm-panel-elevated wm-card-overlay-blue overflow-hidden rounded-2xl border shadow-sm dark:border-slate-700/50 dark:bg-slate-900/40"
                                >
                                    <CardHeader
                                        class="wm-border-default border-b bg-slate-50/40 pb-3 dark:bg-slate-900/60 dark:border-slate-700/50"
                                    >
                                        <CardTitle class="wm-text-primary text-base">
                                            Distribusi Urgensi Backlog Limbah
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <DistributionDonut
                                            :data="wasteBacklogUrgencyDistribution"
                                            total-label="Backlog"
                                            value-suffix="record"
                                        />
                                    </CardContent>
                                </Card>

                                <Card
                                    class="wm-panel-elevated wm-card-overlay-blue overflow-hidden rounded-2xl border shadow-sm dark:border-slate-700/50 dark:bg-slate-900/40"
                                >
                                    <CardHeader
                                        class="wm-border-default border-b bg-slate-50/40 pb-3 dark:bg-slate-900/60 dark:border-slate-700/50"
                                    >
                                        <CardTitle class="wm-text-primary text-base">
                                            Komposisi Saldo FABA
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <DistributionDonut
                                            :data="fabaMaterialBalanceDistribution"
                                            total-label="Saldo"
                                            value-suffix="ton"
                                        />
                                    </CardContent>
                                </Card>

                                <Card
                                    class="wm-panel-elevated wm-card-overlay-blue overflow-hidden rounded-2xl border shadow-sm dark:border-slate-700/50 dark:bg-slate-900/40"
                                >
                                    <CardHeader
                                        class="wm-border-default border-b bg-slate-50/40 pb-3 dark:bg-slate-900/60 dark:border-slate-700/50"
                                    >
                                        <CardTitle class="wm-text-primary text-base">
                                            Komposisi Pemanfaatan FABA
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <DistributionDonut
                                            :data="fabaUtilizationDistribution"
                                            total-label="Pemanfaatan"
                                            value-suffix="ton"
                                        />
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
