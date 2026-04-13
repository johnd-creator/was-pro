<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import CompactStat from '@/components/dashboard/CompactStat.vue';
import ComplianceHero from '@/components/dashboard/ComplianceHero.vue';
import DistributionDonut from '@/components/dashboard/DistributionDonut.vue';
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
    type: 'waste_record' | 'faba_approval';
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
    tasks: DashboardTaskItem[];
    taskContext: 'operator' | 'approver';
    wasteTasks: DashboardTaskItem[];
    fabaTasks: DashboardTaskItem[];
    wastePendingCount: number;
    fabaPendingCount: number;
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
            class="wm-bg-app relative overflow-x-hidden px-4 py-4 sm:px-6 sm:py-5 lg:px-8 lg:py-6"
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

            <div class="space-y-4 lg:space-y-5">
                <section
                    class="grid gap-4 xl:grid-cols-6 xl:grid-rows-[auto_auto] xl:items-start"
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
                            <CompactStat
                                title="FABA Produksi"
                                :value="fabaStats.total_production"
                                unit="ton"
                                :context-label="wasteStatsContext.fabaLabel"
                                hint="Output periode aktif"
                                color="blue"
                            />
                            <CompactStat
                                title="FABA Pemanfaatan"
                                :value="fabaStats.total_utilization"
                                unit="ton"
                                :context-label="wasteStatsContext.fabaLabel"
                                hint="Serapan periode aktif"
                                color="emerald"
                            />
                            <CompactStat
                                title="Saldo FABA"
                                :value="fabaStats.current_balance"
                                unit="ton"
                                :context-label="wasteStatsContext.fabaLabel"
                                hint="Posisi saldo berjalan"
                                :color="
                                    fabaStats.current_balance < 0
                                        ? 'red'
                                        : 'emerald'
                                "
                            />
                        </div>
                    </div>

                    <div class="xl:col-span-2 xl:row-span-2 xl:row-start-1">
                        <TaskListPanel
                            :waste-tasks="wasteTasks"
                            :faba-tasks="fabaTasks"
                            :task-context="taskContext"
                            :waste-pending-count="wastePendingCount"
                            :faba-pending-count="fabaPendingCount"
                        />
                    </div>
                </section>

                <section class="space-y-3 lg:space-y-4">
                    <div class="flex items-end justify-between gap-4">
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
                        </div>
                        <p class="wm-text-secondary hidden text-sm lg:block">
                            Bandingkan ritme input, pengangkutan, produksi, dan
                            pemanfaatan dalam satu area baca.
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
                                class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Composition
                            </p>
                            <h3
                                class="wm-text-primary mt-1 text-base font-semibold tracking-tight"
                            >
                                Distribusi
                            </h3>
                        </div>
                        <p class="wm-text-secondary hidden text-sm lg:block">
                            Baca komposisi kategori dan material tanpa
                            kehilangan konteks angka total.
                        </p>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <Card
                            class="wm-panel-elevated overflow-hidden rounded-2xl border shadow-sm"
                        >
                            <CardHeader
                                class="wm-border-default border-b bg-slate-50/40 pb-3 dark:bg-slate-900/30"
                            >
                                <CardTitle class="wm-text-primary text-base">
                                    Distribusi Kategori Limbah
                                </CardTitle>
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
                            class="wm-panel-elevated overflow-hidden rounded-2xl border shadow-sm"
                        >
                            <CardHeader
                                class="wm-border-default border-b bg-slate-50/40 pb-3 dark:bg-slate-900/30"
                            >
                                <CardTitle class="wm-text-primary text-base">
                                    Distribusi Material Produksi FABA
                                </CardTitle>
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
            </div>
        </div>
    </AppLayout>
</template>
