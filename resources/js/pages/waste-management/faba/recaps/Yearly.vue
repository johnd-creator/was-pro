<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CalendarRange,
    Sparkles,
} from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import FabaBalanceReconciliation from '@/components/FabaBalanceReconciliation.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaYearlyTotals } from '@/types/faba';

const props = defineProps<{
    recap: {
        year: number;
        months: Array<{
            month: number;
            period_label: string;
            total_production: number;
            total_utilization: number;
            closing_balance: number;
            warnings?: Array<{ code: string; message: string }>;
        }>;
        totals: FabaYearlyTotals;
    };
    filters: { year: number };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Rekap Tahunan FABA',
        href: wasteManagementRoutes.faba.recaps.yearly.url(),
    },
];

const form = reactive({ ...props.filters });

const warningPeriods = computed(
    () =>
        props.recap.months.filter((month) => (month.warnings?.length ?? 0) > 0)
            .length,
);

function applyFilters(): void {
    router.get(scopedUrl(wasteManagementRoutes.faba.recaps.yearly()), form);
}
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Rekap Tahunan FABA"
    >
        <Head title="Rekap Tahunan FABA" />

        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="space-y-4">
                <section class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                        <div class="space-y-4">
                            <div class="space-y-3">
                                <Heading title="Rekap Tahunan FABA" />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Tahun
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ recap.year }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-neutral"
                                        >
                                            <CalendarRange class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
                                            >
                                                Produksi
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{
                                                    recap.totals
                                                        .total_production
                                                }}
                                            </p>
                                            <p
                                                class="text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                ton
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-emerald"
                                        >
                                            <Sparkles class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="wm-hero-stat-card wm-hero-stat-rose"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-rose-700/80 uppercase"
                                            >
                                                Periode Warning
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ warningPeriods }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-rose"
                                        >
                                            <AlertTriangle class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-4 rounded-[28px] p-5"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Filter Tahun
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Ganti horizon recap
                                </p>
                            </div>
                            <Input
                                v-model="form.year"
                                type="number"
                                class="h-11 bg-white dark:bg-slate-950"
                            />
                            <Button
                                class="w-full justify-between"
                                @click="applyFilters"
                            >
                                Terapkan Filter
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Saldo Akhir
                                </p>
                                <p
                                    class="mt-2 text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    {{ recap.totals.closing_balance }} ton
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <FabaBalanceReconciliation
                    :summary="recap.totals"
                    :period-label="String(recap.year)"
                    yearly
                />

                <div
                    v-if="warningPeriods > 0"
                    class="rounded-[24px] border border-rose-200/80 bg-rose-50/85 px-5 py-4 text-sm text-rose-950 shadow-sm shadow-rose-100/60"
                >
                    Ada {{ warningPeriods }} periode yang masih memiliki warning
                    operasional. Tinjau bulan terkait sebelum finalisasi laporan
                    tahunan.
                </div>

                <section
                    class="wm-surface-elevated overflow-hidden rounded-[28px]"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-5 py-4 dark:bg-slate-900/80"
                    >
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Breakdown Bulanan
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                Rekap per bulan untuk produksi, pemanfaatan, dan
                                saldo akhir.
                            </p>
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{ recap.months.length }} periode
                        </div>
                    </div>

                    <Table>
                        <TableHeader
                            class="bg-slate-50/90 dark:bg-slate-900/80"
                        >
                            <TableRow
                                class="border-slate-200/80 dark:border-slate-800/80"
                            >
                                <TableHead>Bulan</TableHead>
                                <TableHead>Produksi</TableHead>
                                <TableHead>Pemanfaatan</TableHead>
                                <TableHead>Saldo Akhir</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="month in recap.months"
                                :key="month.month"
                                class="border-slate-200/70 dark:border-slate-800/70"
                            >
                                <TableCell
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    <div>
                                        <div>{{ month.period_label }}</div>
                                        <div
                                            v-if="
                                                (month.warnings?.length ?? 0) >
                                                0
                                            "
                                            class="mt-1 text-xs font-medium text-rose-600"
                                        >
                                            {{ month.warnings?.length }} warning
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>{{
                                    month.total_production
                                }}</TableCell>
                                <TableCell>{{
                                    month.total_utilization
                                }}</TableCell>
                                <TableCell>{{
                                    month.closing_balance
                                }}</TableCell>
                            </TableRow>
                            <TableRow
                                class="border-slate-200/80 bg-slate-50/80 dark:bg-slate-900/70"
                            >
                                <TableCell
                                    class="font-semibold text-slate-950 dark:text-slate-100"
                                    >Total</TableCell
                                >
                                <TableCell
                                    class="font-semibold text-slate-950 dark:text-slate-100"
                                    >{{
                                        recap.totals.total_production
                                    }}</TableCell
                                >
                                <TableCell
                                    class="font-semibold text-slate-950 dark:text-slate-100"
                                    >{{
                                        recap.totals.total_utilization
                                    }}</TableCell
                                >
                                <TableCell
                                    class="font-semibold text-slate-950 dark:text-slate-100"
                                    >{{
                                        recap.totals.closing_balance
                                    }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
