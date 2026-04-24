<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CalendarRange,
    FileBarChart2,
    FileSpreadsheet,
    FileText,
    Layers3,
    Sparkles,
} from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaMaterial, formatFabaMovementType } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaAnalysisMatrix,
    FabaAnomalyItem,
    FabaCapacitySummary,
    FabaMonthlyRecap,
    FabaPurpose,
    FabaStockCardRow,
    FabaVendor,
} from '@/types/faba';

type OptionRef = {
    id: string;
    name: string;
};

const ALL = '__all__';

const props = defineProps<{
    currentYear: number;
    filters: {
        year: number;
        month: number;
        material_type: string | null;
        movement_type: string | null;
        vendor_id: string | null;
        internal_destination_id: string | null;
        purpose_id: string | null;
    };
    availablePeriods: Array<{
        year: number;
        month: number;
        period_label: string;
    }>;
    resolvedFromLatestPeriod: boolean;
    options: {
        materials: string[];
        movementTypes: string[];
        vendors: FabaVendor[];
        internalDestinations: OptionRef[];
        purposes: FabaPurpose[];
    };
    monthlyRecap: FabaMonthlyRecap;
    yearlyRecap: {
        year: number;
        totals: {
            total_production: number;
            total_utilization: number;
            closing_balance: number;
        };
        latest_month?: {
            period_label: string;
            total_production: number;
            total_utilization: number;
            closing_balance: number;
        } | null;
    };
    vendorRecap: {
        vendors: Array<{
            vendor_name: string;
            total_quantity: number;
            transactions_count: number;
        }>;
    };
    internalDestinationRecap: {
        destinations: Array<{
            internal_destination_name: string;
            total_quantity: number;
            transactions_count: number;
        }>;
    };
    purposeRecap: {
        purposes: Array<{
            purpose_name: string;
            total_quantity: number;
            transactions_count: number;
        }>;
    };
    stockCard: {
        rows: FabaStockCardRow[];
        summary: {
            count: number;
            latest_balances: Array<{
                material_type: string;
                balance: number;
            }>;
        };
    };
    anomalyReport: {
        items: FabaAnomalyItem[];
    };
    analysisMatrix: FabaAnalysisMatrix;
    tpsCapacitySummary: FabaCapacitySummary;
}>();

const form = reactive({
    year: props.filters.year,
    month: props.filters.month,
    material_type: props.filters.material_type ?? ALL,
    movement_type: props.filters.movement_type ?? ALL,
    vendor_id: props.filters.vendor_id ?? ALL,
    internal_destination_id: props.filters.internal_destination_id ?? ALL,
    purpose_id: props.filters.purpose_id ?? ALL,
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Laporan FABA',
        href: wasteManagementRoutes.faba.reports.index.url(),
    },
];

const normalizedFilters = computed(() => ({
    year: form.year,
    month: form.month,
    material_type: form.material_type === ALL ? null : form.material_type,
    movement_type: form.movement_type === ALL ? null : form.movement_type,
    vendor_id: form.vendor_id === ALL ? null : form.vendor_id,
    internal_destination_id:
        form.internal_destination_id === ALL
            ? null
            : form.internal_destination_id,
    purpose_id: form.purpose_id === ALL ? null : form.purpose_id,
}));

const activePeriodLabel = computed(
    () =>
        props.availablePeriods.find(
            (item) =>
                item.year === normalizedFilters.value.year &&
                item.month === normalizedFilters.value.month,
        )?.period_label ?? props.monthlyRecap.period_label,
);

const filterSummary = computed(() => {
    const labels = [
        normalizedFilters.value.material_type
            ? formatFabaMaterial(normalizedFilters.value.material_type)
            : null,
        normalizedFilters.value.movement_type
            ? formatFabaMovementType(normalizedFilters.value.movement_type)
            : null,
        normalizedFilters.value.vendor_id
            ? props.options.vendors.find(
                  (item) => item.id === normalizedFilters.value.vendor_id,
              )?.name
            : null,
        normalizedFilters.value.internal_destination_id
            ? props.options.internalDestinations.find(
                  (item) =>
                      item.id ===
                      normalizedFilters.value.internal_destination_id,
              )?.name
            : null,
        normalizedFilters.value.purpose_id
            ? props.options.purposes.find(
                  (item) => item.id === normalizedFilters.value.purpose_id,
              )?.name
            : null,
    ].filter(Boolean);

    return labels.length > 0 ? labels.join(' • ') : 'Semua segmen aktif';
});

const highlightedBalances = computed(() =>
    props.stockCard.summary.latest_balances
        .slice(0, 2)
        .map(
            (item) =>
                `${formatFabaMaterial(item.material_type)} ${item.balance} ton`,
        )
        .join(' • '),
);

function download(url: string): void {
    window.location.assign(url);
}

function analysisMatrixUrl(format: 'xlsx' | 'pdf'): string {
    const params = new URLSearchParams();

    params.set('year', String(normalizedFilters.value.year));

    return `/waste-management/faba/reports/analysis-matrix.${format}?${params.toString()}`;
}

function applyFilters(): void {
    router.get(
        wasteManagementRoutes.faba.reports.index(),
        normalizedFilters.value,
    );
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Laporan FABA">
        <Head title="Laporan FABA" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[380px]"
            />
            <div
                class="pointer-events-none absolute -top-14 left-1/4 -z-10 h-64 w-64 rounded-full bg-cyan-200/20 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-16 right-0 -z-10 h-72 w-72 rounded-full bg-amber-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] to-cyan-50/20 dark:to-cyan-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-cyan-700/70 uppercase"
                                >
                                    Report Center
                                </p>
                                <Heading
                                    title="Laporan FABA"
                                    description="Susun export operasional dan eksekutif dari satu panel yang lebih ringkas, dengan konteks periode, segmentasi, dan risiko aktif yang langsung terbaca."
                                />
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
                                                Periode Aktif
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ activePeriodLabel }}
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
                                    class="wm-hero-stat-card wm-hero-stat-cyan"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-cyan-700/80 uppercase"
                                            >
                                                Paket Laporan
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                8
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-cyan"
                                        >
                                            <FileBarChart2 class="h-5 w-5" />
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
                                                Warning Aktif
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{
                                                    anomalyReport.items.length +
                                                    monthlyRecap.warnings.length
                                                }}
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
                            <div class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Filter Ekspor
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Susun konteks laporan
                                </p>
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Kunci tahun, bulan, dan segmen agar export
                                    yang keluar sesuai snapshot analisis Anda.
                                </p>
                            </div>

                            <div
                                class="grid gap-4 md:grid-cols-2 lg:grid-cols-1"
                            >
                                <div class="grid gap-2">
                                    <Label>Tahun</Label>
                                    <Input
                                        v-model="form.year"
                                        type="number"
                                        class="h-11 bg-white dark:bg-slate-950"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Bulan</Label>
                                    <Input
                                        v-model="form.month"
                                        type="number"
                                        min="1"
                                        max="12"
                                        class="h-11 bg-white dark:bg-slate-950"
                                    />
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            availablePeriods
                                                .filter(
                                                    (item) =>
                                                        item.year === form.year,
                                                )
                                                .map(
                                                    (item) => item.period_label,
                                                )
                                                .join(', ') ||
                                            'Belum ada periode lain pada tahun ini.'
                                        }}
                                    </p>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Material</Label>
                                    <Select v-model="form.material_type">
                                        <SelectTrigger
                                            class="h-11 bg-white dark:bg-slate-950"
                                            ><SelectValue
                                                placeholder="Semua material"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="ALL"
                                                >Semua material</SelectItem
                                            >
                                            <SelectItem
                                                v-for="item in options.materials"
                                                :key="item"
                                                :value="item"
                                            >
                                                {{ formatFabaMaterial(item) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Tipe transaksi</Label>
                                    <Select v-model="form.movement_type">
                                        <SelectTrigger
                                            class="h-11 bg-white dark:bg-slate-950"
                                            ><SelectValue
                                                placeholder="Semua transaksi"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="ALL"
                                                >Semua transaksi</SelectItem
                                            >
                                            <SelectItem
                                                v-for="item in options.movementTypes"
                                                :key="item"
                                                :value="item"
                                            >
                                                {{
                                                    formatFabaMovementType(item)
                                                }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Vendor</Label>
                                    <Select v-model="form.vendor_id">
                                        <SelectTrigger
                                            class="h-11 bg-white dark:bg-slate-950"
                                            ><SelectValue
                                                placeholder="Semua vendor"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="ALL"
                                                >Semua vendor</SelectItem
                                            >
                                            <SelectItem
                                                v-for="vendor in options.vendors"
                                                :key="vendor.id"
                                                :value="vendor.id"
                                            >
                                                {{ vendor.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Tujuan internal</Label>
                                    <Select
                                        v-model="form.internal_destination_id"
                                    >
                                        <SelectTrigger
                                            class="h-11 bg-white dark:bg-slate-950"
                                            ><SelectValue
                                                placeholder="Semua tujuan internal"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="ALL"
                                                >Semua tujuan
                                                internal</SelectItem
                                            >
                                            <SelectItem
                                                v-for="destination in options.internalDestinations"
                                                :key="destination.id"
                                                :value="destination.id"
                                            >
                                                {{ destination.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Tujuan pemanfaatan</Label>
                                    <Select v-model="form.purpose_id">
                                        <SelectTrigger
                                            class="h-11 bg-white dark:bg-slate-950"
                                            ><SelectValue
                                                placeholder="Semua tujuan pemanfaatan"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="ALL"
                                                >Semua tujuan
                                                pemanfaatan</SelectItem
                                            >
                                            <SelectItem
                                                v-for="purpose in options.purposes"
                                                :key="purpose.id"
                                                :value="purpose.id"
                                            >
                                                {{ purpose.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

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
                                    Segmen aktif
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{ filterSummary }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div
                    v-if="resolvedFromLatestPeriod"
                    class="rounded-[24px] border border-cyan-200/80 bg-cyan-50/85 px-5 py-4 text-sm text-cyan-950 shadow-sm shadow-cyan-100/60"
                >
                    Snapshot default diarahkan ke periode terakhir yang memiliki
                    movement agar panel laporan langsung menampilkan data aktif.
                </div>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Laporan Utama
                            </p>
                            <h2
                                class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                Export Snapshot dan Rekap
                            </h2>
                        </div>
                        <div class="wm-chip px-3 py-1.5 text-xs font-medium">
                            4 paket inti
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <article
                            class="rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-cyan-700/75 uppercase"
                                    >
                                        Snapshot Bulanan
                                    </p>
                                    <h3
                                        class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        Rekap Closing Bulanan
                                    </h3>
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Ringkasan bulan aktif untuk produksi,
                                        pemanfaatan, saldo akhir, dan warning
                                        operasional.
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-cyan-200/80 bg-cyan-50/90 p-3 text-cyan-700"
                                >
                                    <CalendarRange class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Produksi
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ monthlyRecap.total_production }} ton
                                    </p>
                                </div>
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Pemanfaatan
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ monthlyRecap.total_utilization }} ton
                                    </p>
                                </div>
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Saldo akhir
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ monthlyRecap.closing_balance }} ton
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <Badge
                                    v-for="warning in monthlyRecap.warnings"
                                    :key="warning.code"
                                    variant="secondary"
                                    class="rounded-full border border-amber-200/80 bg-amber-50/90 text-amber-800"
                                >
                                    {{ warning.message }}
                                </Badge>
                                <span
                                    v-if="monthlyRecap.warnings.length === 0"
                                    class="rounded-full border border-emerald-200/80 bg-emerald-50/90 px-2.5 py-1 text-[11px] font-medium text-emerald-800"
                                >
                                    Tidak ada warning snapshot
                                </span>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.monthly.xlsx.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        month: normalizedFilters.month,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.monthly.pdf.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        month: normalizedFilters.month,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>

                        <article
                            class="rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/75 uppercase"
                                    >
                                        Ringkasan Tahunan
                                    </p>
                                    <h3
                                        class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        Rekap Tahunan
                                    </h3>
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Akumulasi per tahun untuk produksi,
                                        pemanfaatan, dan snapshot saldo akhir
                                        terbaru.
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-emerald-200/80 bg-emerald-50/90 p-3 text-emerald-700"
                                >
                                    <Sparkles class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Produksi
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            yearlyRecap.totals.total_production
                                        }}
                                        ton
                                    </p>
                                </div>
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Pemanfaatan
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            yearlyRecap.totals.total_utilization
                                        }}
                                        ton
                                    </p>
                                </div>
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Saldo akhir
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ yearlyRecap.totals.closing_balance }}
                                        ton
                                    </p>
                                </div>
                            </div>

                            <p
                                v-if="yearlyRecap.latest_month"
                                class="wm-surface-subtle mt-4 rounded-[20px] px-4 py-3 text-sm text-slate-600 dark:text-slate-300"
                            >
                                Snapshot terakhir:
                                {{ yearlyRecap.latest_month.period_label }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.yearly.xlsx.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.yearly.pdf.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Executive Insight
                            </p>
                            <h2
                                class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                Analysis Matrix dan kapasitas TPS
                            </h2>
                        </div>
                        <div class="wm-chip px-3 py-1.5 text-xs font-medium">
                            2 paket insight
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <article
                            class="rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-amber-700/75 uppercase"
                                    >
                                        Analysis Matrix
                                    </p>
                                    <h3 class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                        Realisasi vs target tahunan
                                    </h3>
                                    <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        Baca kinerja pemanfaatan FABA per segmen utama dengan target tahunan dan realisasi kumulatif.
                                    </p>
                                </div>
                                <div class="rounded-2xl border border-amber-200/80 bg-amber-50/90 p-3 text-amber-700">
                                    <FileBarChart2 class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="wm-surface-subtle rounded-[20px] px-4 py-3">
                                    <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        Target
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                        {{ analysisMatrix.summary.total_target_quantity }} ton
                                    </p>
                                </div>
                                <div class="wm-surface-subtle rounded-[20px] px-4 py-3">
                                    <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        Realisasi
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                        {{ analysisMatrix.summary.total_actual_quantity }} ton
                                    </p>
                                </div>
                                <div class="wm-surface-subtle rounded-[20px] px-4 py-3">
                                    <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        Rata-rata
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                        {{ analysisMatrix.summary.average_achievement_percentage }}%
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div
                                    v-for="segment in analysisMatrix.segments.slice(0, 4)"
                                    :key="segment.key"
                                    class="wm-surface-subtle flex items-center justify-between rounded-[18px] px-4 py-3"
                                >
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-100">
                                            {{ segment.label }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            {{ segment.actual_quantity }} / {{ segment.target_quantity }} ton
                                        </p>
                                    </div>
                                    <Badge variant="secondary" class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700">
                                        {{ segment.achievement_percentage }}%
                                    </Badge>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button @click="download(analysisMatrixUrl('xlsx'))">
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button variant="outline" @click="download(analysisMatrixUrl('pdf'))">
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>

                        <article
                            class="rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <p class="text-[11px] font-semibold tracking-[0.16em] text-cyan-700/75 uppercase">
                                        Kapasitas TPS
                                    </p>
                                    <h3 class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                        Snapshot utilisasi penyimpanan
                                    </h3>
                                    <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        Posisi saldo aktif dibanding kapasitas TPS untuk tiap material dan total periode.
                                    </p>
                                </div>
                                <div class="rounded-2xl border border-cyan-200/80 bg-cyan-50/90 p-3 text-cyan-700">
                                    <Layers3 class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3">
                                <div
                                    v-for="item in tpsCapacitySummary.materials"
                                    :key="item.material_type"
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950 dark:text-slate-100">
                                                {{ formatFabaMaterial(item.material_type) }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ item.balance }} dari {{ item.capacity }} ton
                                            </p>
                                        </div>
                                        <Badge variant="secondary" class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700">
                                            {{ item.utilization_percentage }}%
                                        </Badge>
                                    </div>
                                </div>
                                <div class="wm-surface-subtle rounded-[20px] px-4 py-3">
                                    <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        Total TPS
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                        {{ tpsCapacitySummary.total.balance }} / {{ tpsCapacitySummary.total.capacity }} ton
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Utilisasi {{ tpsCapacitySummary.total.utilization_percentage }}%
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Rekap Distribusi
                            </p>
                            <h2
                                class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                Vendor, internal, dan tujuan
                            </h2>
                        </div>
                        <div class="wm-chip px-3 py-1.5 text-xs font-medium">
                            3 paket distribusi
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <article class="wm-surface-elevated rounded-[26px] p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/75 uppercase"
                                    >
                                        Eksternal
                                    </p>
                                    <h3
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        Rekap Vendor
                                    </h3>
                                </div>
                                <div
                                    class="rounded-2xl border border-blue-200/80 bg-blue-50/90 p-3 text-blue-700"
                                >
                                    <Layers3 class="h-5 w-5" />
                                </div>
                            </div>
                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ vendorRecap.vendors.length }} vendor aktif
                                dalam tahun filter saat ini.
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.vendors.xlsx.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        vendor_id:
                                                            normalizedFilters.vendor_id,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.vendors.pdf.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        vendor_id:
                                                            normalizedFilters.vendor_id,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>

                        <article class="wm-surface-elevated rounded-[26px] p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-violet-700/75 uppercase"
                                    >
                                        Internal
                                    </p>
                                    <h3
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        Tujuan Internal
                                    </h3>
                                </div>
                                <div
                                    class="rounded-2xl border border-violet-200/80 bg-violet-50/90 p-3 text-violet-700"
                                >
                                    <Layers3 class="h-5 w-5" />
                                </div>
                            </div>
                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    internalDestinationRecap.destinations.length
                                }}
                                tujuan internal tampil pada filter tahun aktif.
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.internalDestinations.xlsx.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        internal_destination_id:
                                                            normalizedFilters.internal_destination_id,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.internalDestinations.pdf.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        internal_destination_id:
                                                            normalizedFilters.internal_destination_id,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>

                        <article class="wm-surface-elevated rounded-[26px] p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-amber-700/75 uppercase"
                                    >
                                        Pemanfaatan
                                    </p>
                                    <h3
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        Tujuan Akhir
                                    </h3>
                                </div>
                                <div
                                    class="rounded-2xl border border-amber-200/80 bg-amber-50/90 p-3 text-amber-700"
                                >
                                    <Layers3 class="h-5 w-5" />
                                </div>
                            </div>
                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ purposeRecap.purposes.length }} tujuan
                                pemanfaatan terpetakan dalam filter aktif.
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.purposes.xlsx.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        purpose_id:
                                                            normalizedFilters.purpose_id,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.purposes.pdf.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        purpose_id:
                                                            normalizedFilters.purpose_id,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Audit Operasional
                            </p>
                            <h2
                                class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                Stock card dan anomaly
                            </h2>
                        </div>
                        <div class="wm-chip px-3 py-1.5 text-xs font-medium">
                            2 paket audit
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <article
                            class="rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Ledger Material
                                    </p>
                                    <h3
                                        class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        Stock Card
                                    </h3>
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Tampilkan movement dan saldo berjalan
                                        untuk audit material per periode.
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-slate-200/80 bg-slate-50/90 p-3 text-slate-700 dark:text-slate-200"
                                >
                                    <FileBarChart2 class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Movement
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ stockCard.summary.count }}
                                    </p>
                                </div>
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Saldo aktif
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 font-medium text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            highlightedBalances ||
                                            'Belum ada saldo aktif'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.stockCard.xlsx.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        month: normalizedFilters.month,
                                                        material_type:
                                                            normalizedFilters.material_type,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.stockCard.pdf.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        month: normalizedFilters.month,
                                                        material_type:
                                                            normalizedFilters.material_type,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>

                        <article
                            class="rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-rose-700/75 uppercase"
                                    >
                                        Audit Warning
                                    </p>
                                    <h3
                                        class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        Laporan Anomali
                                    </h3>
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Rekap item yang perlu ditinjau ulang
                                        sebelum laporan atau closing dipakai
                                        untuk keputusan.
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-rose-200/80 bg-rose-50/90 p-3 text-rose-700"
                                >
                                    <AlertTriangle class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Anomali
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ anomalyReport.items.length }}
                                    </p>
                                </div>
                                <div
                                    class="wm-surface-subtle rounded-[20px] px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Status
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 font-medium text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            anomalyReport.items.length > 0
                                                ? 'Perlu review lanjutan'
                                                : 'Tidak ada temuan pada filter ini'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.anomalies.xlsx.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        month: normalizedFilters.month,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                                    Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="
                                        download(
                                            wasteManagementRoutes.faba.reports.anomalies.pdf.url(
                                                {
                                                    query: {
                                                        year: normalizedFilters.year,
                                                        month: normalizedFilters.month,
                                                    },
                                                },
                                            ),
                                        )
                                    "
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    PDF
                                </Button>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
