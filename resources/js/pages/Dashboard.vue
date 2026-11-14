<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpRight, CalendarDays, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import { index as notificationIndex } from '@/actions/App/Http/Controllers/WasteManagement/OperationalInboxController';
import CompactStat from '@/components/dashboard/CompactStat.vue';
import ComplianceHero from '@/components/dashboard/ComplianceHero.vue';
import DistributionSummary from '@/components/dashboard/DistributionSummary.vue';
import FabaTrendChart from '@/components/dashboard/FabaTrendChart.vue';
import TaskListPanel from '@/components/dashboard/TaskListPanel.vue';
import WasteFlowChart from '@/components/dashboard/WasteFlowChart.vue';

// UI components
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard as dashboardRoute } from '@/routes';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem, FabaChartData, WasteChartData } from '@/types';
import type {
    OperationalAlert,
    NotificationSummary,
} from '@/types/notifications';

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
    submitted_at?: string | null;
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
    operationalAlerts: OperationalAlert[];
    notificationSummary: NotificationSummary;
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
        current_fly_ash_balance: number;
    };
    fabaChart: FabaChartData[];
    fabaMaterialBalanceDistribution: CategoryData[];
    fabaUtilizationDistribution: CategoryData[];
    fabaPendingApprovals: ApprovalItem[];
    fabaWarnings: Array<{
        month: number;
        period_label: string;
        message: string;
        closing_balance: number;
    }>;
    wasteChart: WasteChartData[];
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
const { hasPermission } = usePermissions();
const { scopedUrl } = useOrganizationContext();
const canViewRecords = computed(
    () =>
        hasPermission('waste_records.view_all') ||
        hasPermission('waste_records.view_own'),
);
function recordsHref(attention?: string): string | undefined {
    return canViewRecords.value
        ? wasteManagementRoutes.records.index.url({
              query: {
                  month: props.filters.month,
                  ...(attention ? { attention } : {}),
              },
          })
        : undefined;
}
const notificationHref = computed(() =>
    notificationIndex.url({ query: { month: props.filters.month } }),
);
const criticalActionHref = computed(() =>
    props.stats.expired_waste > 0
        ? recordsHref('expired')
        : notificationHref.value,
);

const wasteStatsContext = computed(() => {
    const snapshotLabel = props.header.snapshot_month_label ?? 'periode aktif';
    return {
        label: 'Hingga periode terpilih',
        totalHint: `Tercatat sampai ${snapshotLabel}`,
        transportedHint: `Sudah terangkut valid`,
        backlogHint: `Termasuk sisa periode sebelumnya`,
        fabaLabel: props.header.snapshot_month_label ?? 'Periode terpilih',
    };
});

const isRefreshing = ref(false);
const refreshError = ref(false);
const selectedMonth = ref(props.filters.month ?? '');
watch(
    () => props.filters.month,
    (month) => {
        selectedMonth.value = month ?? '';
    },
);

function changePeriod(): void {
    if (
        isRefreshing.value ||
        !props.availableMonths.some(
            (month) => month.value === selectedMonth.value,
        ) ||
        selectedMonth.value === props.filters.month
    )
        return;
    refreshError.value = false;
    router.get(
        scopedUrl(dashboardRoute.url()),
        { month: selectedMonth.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isRefreshing.value = true;
            },
            onError: () => {
                refreshError.value = true;
            },
            onFinish: () => {
                selectedMonth.value = props.filters.month ?? '';
                isRefreshing.value = false;
            },
        },
    );
}
const annualFabaStats = computed(() => [
    { label: 'Produksi', value: props.fabaHeroStats.total_production },
    { label: 'Pemanfaatan', value: props.fabaHeroStats.total_utilization },
    {
        label: 'Saldo Fly Ash',
        value: props.fabaHeroStats.current_fly_ash_balance,
    },
]);

function refreshDashboard(): void {
    refreshError.value = false;
    router.reload({
        onStart: () => {
            isRefreshing.value = true;
        },
        onError: () => {
            refreshError.value = true;
        },
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-[1680px] space-y-4 p-4"
            :aria-busy="isRefreshing"
        >
            <p
                v-if="refreshError"
                role="alert"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
            >
                Ringkasan belum dapat diperbarui. Data sebelumnya tetap
                ditampilkan. Coba perbarui kembali.
            </p>
            <p class="sr-only" role="status" aria-live="polite">
                {{
                    isRefreshing
                        ? 'Memperbarui ringkasan operasional.'
                        : 'Ringkasan operasional siap.'
                }}
            </p>
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800"
            >
                <nav aria-label="Bagian dashboard" class="flex flex-wrap gap-1">
                    <a
                        v-for="section in [
                            { id: 'ringkasan', label: 'Ringkasan' },
                            { id: 'limbah', label: 'Limbah B3' },
                            { id: 'faba', label: 'FABA' },
                        ]"
                        :key="section.id"
                        :href="`#${section.id}`"
                        class="inline-flex min-h-11 items-center gap-2 rounded-t-lg px-4 text-sm font-medium text-slate-600 hover:bg-white hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-blue-300"
                        >{{ section.label
                        }}<ArrowUpRight
                            class="size-3.5 text-slate-400"
                            aria-hidden="true"
                    /></a>
                </nav>
                <div class="flex flex-wrap items-center gap-2 pb-1">
                    <label for="dashboard-period" class="sr-only"
                        >Periode ringkasan</label
                    >
                    <div
                        class="flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <CalendarDays
                            class="size-4 shrink-0 text-slate-400"
                            aria-hidden="true"
                        />
                        <select
                            id="dashboard-period"
                            v-model="selectedMonth"
                            :disabled="isRefreshing || !availableMonths.length"
                            class="min-h-10 bg-transparent font-semibold focus-visible:outline-2 focus-visible:outline-blue-600 disabled:cursor-wait"
                            @change="changePeriod"
                        >
                            <option
                                v-for="month in availableMonths"
                                :key="month.value"
                                :value="month.value"
                            >
                                {{ month.label }}
                            </option>
                        </select>
                    </div>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-wait disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        :disabled="isRefreshing"
                        @click="refreshDashboard"
                    >
                        <RefreshCw
                            class="size-4"
                            :class="{
                                'animate-spin motion-reduce:animate-none':
                                    isRefreshing,
                            }"
                            aria-hidden="true"
                        />
                        {{ isRefreshing ? 'Memperbarui.' : 'Perbarui' }}
                    </button>
                </div>
            </div>
            <section
                id="ringkasan"
                aria-label="Ringkasan periode terpilih"
                class="scroll-mt-24 space-y-4"
            >
                <ComplianceHero
                    :total-indicators="notificationSummary.total_count"
                    :pending-hauling-approvals="
                        notificationSummary.pending_hauling_approvals_count ?? 0
                    "
                    :pending-hauling-plans="
                        notificationSummary.pending_hauling_plans_count ?? 0
                    "
                    :expired-waste="stats.expired_waste"
                    :expiring-soon-waste="stats.expiring_soon_waste"
                    :pending-waste-approvals="
                        notificationSummary.pending_waste_approvals_count
                    "
                    :pending-faba-approvals="
                        notificationSummary.pending_faba_approvals_count
                    "
                    :faba-warnings="notificationSummary.faba_warnings_count"
                    :risk-status="header.risk_status"
                    :risk-label="header.risk_label"
                    :risk-tone="header.risk_tone"
                    :show-metric-cards="false"
                    :action-href="criticalActionHref"
                    :inbox-href="notificationHref"
                />
                <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <CompactStat
                        title="Total Catatan Limbah"
                        :href="recordsHref()"
                        :value="stats.waste_total_records_snapshot"
                        :context-label="wasteStatsContext.label"
                        :hint="wasteStatsContext.totalHint"
                    />
                    <CompactStat
                        title="Menunggu Ditinjau"
                        :href="recordsHref('pending')"
                        :value="stats.pending_records"
                        context-label="Periode terpilih"
                        hint="Catatan yang menunggu pemeriksaan"
                        :color="stats.pending_records > 0 ? 'orange' : 'blue'"
                    />
                    <CompactStat
                        title="Belum Terangkut"
                        :href="recordsHref('backlog')"
                        :value="stats.waste_untransported_records_snapshot"
                        :context-label="wasteStatsContext.label"
                        hint="Termasuk sisa dari periode sebelumnya"
                    />
                    <CompactStat
                        title="Melewati Batas Simpan"
                        :href="recordsHref('expired')"
                        :value="stats.expired_waste"
                        context-label="Kepatuhan"
                        hint="Catatan yang perlu ditindaklanjuti"
                        :color="stats.expired_waste > 0 ? 'red' : 'emerald'"
                    />
                </div>
            </section>
            <section
                id="limbah"
                aria-labelledby="limbah-title"
                class="scroll-mt-24 space-y-4"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            01 / Limbah B3
                        </p>
                        <h2
                            id="limbah-title"
                            class="mt-1 text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            Rekapitulasi Limbah dan Tindak Lanjut
                        </h2>
                    </div>
                    <p
                        class="max-w-lg text-sm leading-6 text-slate-500 dark:text-slate-400"
                    >
                        Limbah masuk dan pengangkutan enam bulan, dengan sisa
                        limbah pada periode terpilih.
                    </p>
                </div>
                <div
                    class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px]"
                >
                    <WasteFlowChart
                        :data="wasteChart"
                        :organization-id="filters.organization_id"
                    />
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
                <div
                    data-testid="dashboard-status-panels"
                    class="grid items-start gap-4 lg:grid-cols-3"
                >
                    <Card
                        class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="px-4 py-3"
                            ><CardTitle class="text-base"
                                >Pengangkutan limbah</CardTitle
                            >
                            <p class="text-xs text-slate-500">
                                Jumlah catatan, mencakup semua satuan.
                            </p></CardHeader
                        >
                        <CardContent class="px-4 pb-4"
                            ><DistributionSummary
                                :data="wasteHaulingStatusDistribution"
                                unit="catatan"
                        /></CardContent>
                    </Card>
                    <Card
                        class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="px-4 py-3"
                            ><CardTitle class="text-base"
                                >Batas penyimpanan</CardTitle
                            >
                            <p class="text-xs text-slate-500">
                                Sisa limbah pada periode yang dipilih.
                            </p></CardHeader
                        >
                        <CardContent class="px-4 pb-4"
                            ><DistributionSummary
                                :data="wasteBacklogUrgencyDistribution"
                                unit="catatan"
                        /></CardContent>
                    </Card>
                    <Card
                        class="min-w-0 gap-0 rounded-2xl border-blue-200 bg-blue-50/50 py-0 shadow-none dark:border-blue-900 dark:bg-blue-950/20"
                    >
                        <CardHeader class="px-4 py-3"
                            ><CardTitle class="text-base"
                                >Belum selesai diangkut</CardTitle
                            >
                            <p class="text-xs text-slate-500">
                                {{ wasteStatsContext.label }}
                            </p></CardHeader
                        >
                        <CardContent class="px-4 pb-4"
                            ><p
                                class="text-4xl font-semibold tracking-tight text-slate-950 tabular-nums dark:text-white"
                            >
                                {{
                                    stats.waste_untransported_records_snapshot.toLocaleString(
                                        'id-ID',
                                    )
                                }}
                                <span class="text-sm font-normal text-slate-500"
                                    >catatan</span
                                >
                            </p>
                            <p
                                class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                            >
                                Masih memiliki sisa limbah, termasuk catatan
                                yang baru diangkut sebagian.
                            </p></CardContent
                        >
                    </Card>
                </div>
            </section>
            <section
                id="faba"
                aria-labelledby="faba-title"
                class="scroll-mt-24 space-y-4"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            02 / Material FABA
                        </p>
                        <h2
                            id="faba-title"
                            class="mt-1 text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            Neraca material &amp; kapasitas TPS
                        </h2>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Fly Ash dan Bottom Ash · ton
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="mb-4 text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Produksi dan pemanfaatan tahun {{ fabaHeroStats.year }}.
                        Saldo Fly Ash memakai periode FABA terbaru, bukan
                        periode ringkasan yang dipilih.
                    </p>
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div v-for="stat in annualFabaStats" :key="stat.label">
                            <dt
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ stat.label }}
                            </dt>
                            <dd
                                class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 tabular-nums dark:text-white"
                            >
                                {{
                                    stat.value.toLocaleString('id-ID', {
                                        maximumFractionDigits: 2,
                                    })
                                }}
                                <span
                                    class="text-sm font-normal text-slate-500 dark:text-slate-400"
                                    >ton</span
                                >
                            </dd>
                        </div>
                    </dl>
                </div>
                <FabaTrendChart :data="fabaChart" />
                <div
                    data-testid="dashboard-faba-composition"
                    class="grid items-start gap-4 lg:grid-cols-3"
                >
                    <Card
                        v-for="material in ['Fly Ash', 'Bottom Ash']"
                        :key="material"
                        class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="px-4 py-3"
                            ><CardTitle class="text-base"
                                >Saldo {{ material }}</CardTitle
                            >
                            <p class="text-xs text-slate-500">
                                {{
                                    material === 'Fly Ash'
                                        ? 'Abu terbang yang masih tersimpan.'
                                        : 'Abu dasar yang masih tersimpan.'
                                }}
                            </p></CardHeader
                        >
                        <CardContent class="px-4 pb-4"
                            ><DistributionSummary
                                :data="
                                    fabaMaterialBalanceDistribution.filter(
                                        (item) => item.label === material,
                                    )
                                "
                                unit="ton"
                                empty-label="Belum ada saldo material pada snapshot ini."
                                prominent
                        /></CardContent>
                    </Card>
                    <Card
                        class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="px-4 py-3"
                            ><CardTitle class="text-base"
                                >Pemanfaatan material</CardTitle
                            >
                            <p class="text-xs text-slate-500">
                                Material yang disalurkan untuk digunakan.
                            </p></CardHeader
                        >
                        <CardContent class="px-4 pb-4"
                            ><DistributionSummary
                                :data="fabaUtilizationDistribution"
                                unit="ton"
                                empty-label="Belum ada pemanfaatan pada snapshot ini."
                        /></CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
