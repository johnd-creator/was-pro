<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, AlertTriangle } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import Link from '@/components/OrganizationLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
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
        balance_as_of_date?: string;
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

const filterForm = reactive({ year: props.year });
const loading = ref(false);
const loadError = ref(false);
watch(
    () => props.year,
    (year) => {
        filterForm.year = year;
    },
);
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Dashboard FABA',
        href: wasteManagementRoutes.faba.dashboard.url(),
    },
];
const latestOperationalMovements = computed(() =>
    props.latestMovements.slice(0, 6),
);
const trendRows = computed(() =>
    props.trend
        .slice(-6)
        .map((item) => ({ ...item, gap: item.production - item.utilization })),
);
const trendMax = computed(() =>
    Math.max(
        1,
        ...trendRows.value.flatMap((item) => [
            item.production,
            item.utilization,
        ]),
    ),
);
const annualStats = computed(() => [
    {
        label: 'Material dihasilkan',
        value: props.stats.total_production,
        hint: `Akumulasi produksi tahun ${props.year}`,
    },
    {
        label: 'Sudah dimanfaatkan',
        value: props.stats.total_utilization,
        hint: `Akumulasi pemanfaatan tahun ${props.year}`,
    },
    {
        label: 'Saldo FABA tahun terpilih',
        value: props.stats.current_balance,
        hint: `Tahun ${props.year}, berdasarkan transaksi disetujui yang tercatat hingga batas ${formatFabaDate(props.stats.balance_as_of_date ?? `${props.year}-12-31`)}, bukan hasil pengukuran fisik TPS`,
    },
]);
const capacityCards = computed(() => [
    { ...props.tpsCapacitySummary.total, label: 'Total penyimpanan' },
    ...props.tpsCapacitySummary.materials.map((item) => ({
        ...item,
        label: formatFabaMaterial(item.material_type),
    })),
]);
function capacityStatus(item: {
    capacity: number;
    utilization_percentage: number;
    status: string;
}): string {
    if (!(item.capacity > 0)) return 'unavailable';
    return item.utilization_percentage > 100 ? 'critical' : item.status;
}
function capacityLabel(item: {
    capacity: number;
    utilization_percentage: number;
    status: string;
}): string {
    if (!(item.capacity > 0)) return 'Kapasitas belum diatur';
    if (item.utilization_percentage > 100) return 'Melebihi kapasitas';
    return item.status === 'critical'
        ? 'Kritis'
        : item.status === 'warning'
          ? 'Mendekati batas'
          : 'Dalam batas';
}
function applyFilters(): void {
    router.get(scopedUrl(wasteManagementRoutes.faba.dashboard()), filterForm, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            loading.value = true;
            loadError.value = false;
        },
        onError: () => {
            loadError.value = true;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}
function formatNumber(value: number): string {
    return value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}
function formatPercentage(value: number): string {
    return `${formatNumber(value)}%`;
}
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Dashboard FABA"
    >
        <Head title="Dashboard FABA" />
        <div
            class="mx-auto w-full max-w-[1600px] space-y-7 px-4 py-6 sm:px-6 lg:px-8"
            :aria-busy="loading"
        >
            <header
                class="flex flex-wrap items-start justify-between gap-5 border-b border-slate-200 pb-6 dark:border-slate-800"
            >
                <div class="max-w-2xl">
                    <p
                        class="text-xs font-semibold tracking-widest text-blue-700 uppercase dark:text-blue-300"
                    >
                        Fly Ash &amp; Bottom Ash
                    </p>
                    <h1
                        class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                    >
                        Dashboard FABA
                    </h1>
                    <p
                        class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400"
                    >
                        Lihat material yang dihasilkan, sudah dimanfaatkan, dan
                        masih tersimpan. Pilih tahun untuk meninjau data
                        organisasi ini.
                    </p>
                </div>
                <form
                    class="flex items-end gap-2"
                    @submit.prevent="applyFilters"
                >
                    <div class="space-y-1.5">
                        <Label for="faba-year">Tahun laporan</Label
                        ><Input
                            id="faba-year"
                            v-model="filterForm.year"
                            class="h-11 w-28"
                            type="number"
                            required
                        />
                    </div>
                    <Button
                        class="min-h-11"
                        :disabled="loading"
                        type="submit"
                        >{{ loading ? 'Memuat…' : 'Terapkan tahun' }}</Button
                    >
                </form>
            </header>
            <p
                v-if="loadError"
                role="alert"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
            >
                Data belum dapat diperbarui. Ringkasan tahun {{ year }} tetap
                ditampilkan; coba terapkan tahun kembali.
            </p>
            <section
                aria-label="Ringkasan tahunan"
                data-testid="faba-annual-summary"
                class="grid gap-4 lg:grid-cols-3"
            >
                <article
                    v-for="(item, index) in annualStats"
                    :key="item.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300"
                    >
                        <span
                            class="size-2 rounded-full"
                            :class="
                                index === 1 ? 'bg-emerald-500' : 'bg-blue-500'
                            "
                        />{{ item.label }}
                    </p>
                    <p
                        class="mt-4 text-3xl font-semibold tracking-tight tabular-nums"
                        :class="
                            item.value < 0
                                ? 'text-red-700 dark:text-red-300'
                                : 'text-slate-950 dark:text-white'
                        "
                    >
                        {{ formatNumber(item.value) }}
                        <span class="text-sm font-normal text-slate-500"
                            >ton</span
                        >
                    </p>
                    <p class="mt-2 text-xs text-slate-500">{{ item.hint }}</p>
                </article>
            </section>
            <section aria-labelledby="storage-heading" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2
                            id="storage-heading"
                            class="text-lg font-semibold text-slate-950 dark:text-white"
                        >
                            Seberapa penuh tempat penyimpanan?
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Posisi {{ tpsCapacitySummary.period.period_label }}.
                            Kapasitas dan batas peringatan mengikuti konfigurasi
                            TPS.
                        </p>
                    </div>
                    <p class="text-xs text-slate-500">
                        Perhatian {{ tpsCapacitySummary.thresholds.warning }}% ·
                        kritis {{ tpsCapacitySummary.thresholds.critical }}%
                    </p>
                </div>
                <div
                    data-testid="faba-capacity-cards"
                    class="grid gap-4 lg:grid-cols-3"
                >
                    <article
                        v-for="item in capacityCards"
                        :key="item.label"
                        class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <h3
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                {{ item.label }}
                            </h3>
                            <span
                                class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="
                                    capacityStatus(item) === 'critical'
                                        ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-200'
                                        : capacityStatus(item) === 'warning'
                                          ? 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
                                          : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                                >{{ capacityLabel(item) }}</span
                            >
                        </div>
                        <p
                            class="mt-4 text-2xl font-semibold text-slate-950 tabular-nums dark:text-white"
                        >
                            {{ formatNumber(item.balance) }}
                            <span class="text-sm font-normal text-slate-500"
                                >ton</span
                            >
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{
                                item.capacity > 0
                                    ? `dari kapasitas ${formatNumber(item.capacity)} ton`
                                    : 'Kapasitas belum tersedia; persentase tidak dihitung.'
                            }}
                        </p>
                        <template v-if="item.capacity > 0"
                            ><div
                                class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                aria-hidden="true"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="
                                        capacityStatus(item) === 'critical'
                                            ? 'bg-red-500'
                                            : capacityStatus(item) === 'warning'
                                              ? 'bg-amber-500'
                                              : 'bg-blue-500'
                                    "
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, item.utilization_percentage))}%`,
                                    }"
                                />
                            </div>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    formatPercentage(
                                        item.utilization_percentage,
                                    )
                                }}
                                terisi
                            </p></template
                        >
                    </article>
                </div>
            </section>
            <section
                aria-labelledby="integrity-heading"
                class="rounded-2xl border px-5 py-4"
                :class="
                    warnings.length || stats.negative_periods
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30'
                        : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900'
                "
            >
                <h2
                    id="integrity-heading"
                    class="flex items-center gap-2 text-sm font-semibold text-slate-950 dark:text-white"
                >
                    <AlertTriangle
                        v-if="warnings.length || stats.negative_periods"
                        class="size-4 text-amber-700"
                    />Konsistensi data
                </h2>
                <p
                    v-if="stats.negative_periods"
                    class="mt-2 text-sm text-amber-900 dark:text-amber-200"
                >
                    {{ stats.negative_periods }} periode memiliki saldo negatif.
                    Periksa transaksi dan lakukan rekonsiliasi.
                </p>
                <ul
                    v-if="warnings.length"
                    class="mt-2 space-y-2 text-sm text-amber-900 dark:text-amber-200"
                >
                    <li
                        v-for="item in warnings"
                        :key="`${item.month}-${item.message}`"
                    >
                        <strong>{{ item.period_label }}:</strong>
                        {{ item.message }} · saldo
                        {{ formatNumber(item.closing_balance) }} ton
                    </li>
                </ul>
                <p
                    v-if="!warnings.length && !stats.negative_periods"
                    class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                >
                    Tidak ada peringatan integritas data pada tahun {{ year }}.
                </p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Pemeriksaan saldo ini terpisah dari peringatan kapasitas
                    penyimpanan di atas.
                </p>
            </section>
            <section
                aria-labelledby="trend-heading"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 px-5 py-5"
                >
                    <div>
                        <h2
                            id="trend-heading"
                            class="text-lg font-semibold text-slate-950 dark:text-white"
                        >
                            Produksi dan pemanfaatan per bulan
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Enam periode terakhir pada tahun {{ year }} ·
                            seluruh nilai dalam ton.
                        </p>
                    </div>
                    <div v-if="latestApprovedPeriod" class="text-sm">
                        <p class="text-xs text-slate-500">
                            Periode terakhir disetujui
                        </p>
                        <Link
                            :href="
                                wasteManagementRoutes.faba.approvals.review([
                                    latestApprovedPeriod.year,
                                    latestApprovedPeriod.month,
                                ]).url
                            "
                            class="mt-1 inline-flex min-h-11 items-center gap-2 font-medium text-blue-700 dark:text-blue-300"
                            >{{ latestApprovedPeriod.period_label
                            }}<ArrowRight class="size-4" /></Link
                        ><Badge variant="secondary" class="ml-2">{{
                            formatFabaStatus(latestApprovedPeriod.status)
                        }}</Badge>
                    </div>
                    <p v-else class="text-xs text-slate-500">
                        Belum ada periode yang disetujui.
                    </p>
                </div>
                <p
                    v-if="!trendRows.length"
                    class="px-5 pb-5 text-sm text-slate-500"
                >
                    Belum ada data produksi atau pemanfaatan pada tahun ini.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-left text-sm">
                        <caption class="sr-only">
                            Perbandingan produksi, pemanfaatan dan saldo dalam
                            ton
                        </caption>
                        <thead
                            class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3">Bulan</th>
                                <th scope="col" class="px-5 py-3">
                                    Dihasilkan
                                </th>
                                <th scope="col" class="px-5 py-3">
                                    Dimanfaatkan
                                </th>
                                <th scope="col" class="px-5 py-3 text-right">
                                    Sisa saldo
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr v-for="item in trendRows" :key="item.month">
                                <th
                                    scope="row"
                                    class="px-5 py-4 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ item.label }}
                                </th>
                                <td class="px-5 py-4">
                                    <span
                                        class="font-semibold text-blue-700 tabular-nums dark:text-blue-300"
                                        >{{
                                            formatNumber(item.production)
                                        }}</span
                                    >
                                    <div
                                        class="mt-2 h-1.5 w-28 rounded-full bg-blue-50 dark:bg-blue-950"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-blue-500"
                                            :style="{
                                                width: `${Math.min(100, Math.max(0, (item.production / trendMax) * 100))}%`,
                                            }"
                                        />
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="font-semibold text-emerald-700 tabular-nums dark:text-emerald-300"
                                        >{{
                                            formatNumber(item.utilization)
                                        }}</span
                                    >
                                    <div
                                        class="mt-2 h-1.5 w-28 rounded-full bg-emerald-50 dark:bg-emerald-950"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-emerald-500"
                                            :style="{
                                                width: `${Math.min(100, Math.max(0, (item.utilization / trendMax) * 100))}%`,
                                            }"
                                        />
                                    </div>
                                </td>
                                <td
                                    class="px-5 py-4 text-right font-semibold tabular-nums"
                                    :class="
                                        item.closing_balance < 0
                                            ? 'text-red-700 dark:text-red-300'
                                            : 'text-slate-900 dark:text-white'
                                    "
                                >
                                    {{ formatNumber(item.closing_balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section aria-labelledby="next-heading" class="space-y-4">
                <div>
                    <h2
                        id="next-heading"
                        class="text-lg font-semibold text-slate-950 dark:text-white"
                    >
                        Pekerjaan berikutnya
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Periksa transaksi, ajukan rekap, lalu tinjau persetujuan
                        periode.
                    </p>
                </div>
                <div class="grid items-start gap-4 xl:grid-cols-3">
                    <Card
                        class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="px-5 py-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-sky-700/70 uppercase"
                            >
                                Pemeriksaan transaksi
                            </p>
                            <CardTitle class="text-lg tracking-tight">
                                Transaksi perlu diperiksa
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-5 pb-5">
                            <div
                                v-if="pendingTransactionApprovals.length === 0"
                                class="rounded-xl bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:bg-slate-950 dark:text-slate-400"
                            >
                                Tidak ada transaksi harian yang menunggu
                                persetujuan.
                            </div>
                            <div v-else class="space-y-3">
                                <Link
                                    v-for="item in pendingTransactionApprovals"
                                    :key="item.id"
                                    :href="
                                        item.movement_type.startsWith(
                                            'adjustment',
                                        )
                                            ? wasteManagementRoutes.faba.adjustments.show(
                                                  item.id,
                                              ).url
                                            : item.movement_type.startsWith(
                                                    'utilization',
                                                )
                                              ? wasteManagementRoutes.faba.utilization.show(
                                                    item.id,
                                                ).url
                                              : wasteManagementRoutes.faba.production.show(
                                                    item.id,
                                                ).url
                                    "
                                    class="group block rounded-xl px-3 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-blue-600 dark:hover:bg-slate-800"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="font-semibold text-slate-950 dark:text-slate-100"
                                            >
                                                {{ item.display_number }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
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
                                                •
                                                {{ item.period_label }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p
                                                class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                            >
                                                {{
                                                    formatNumber(item.quantity)
                                                }}
                                                {{ item.unit }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    item.created_by_user
                                                        ?.name ?? 'Sistem'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </CardContent> </Card
                    ><Card
                        class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="px-5 py-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                            >
                                Pengajuan rekap
                            </p>
                            <CardTitle class="text-lg tracking-tight">
                                Periode siap diajukan
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-5 pb-5">
                            <div
                                v-if="readyClosingPeriods.length === 0"
                                class="rounded-xl bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:bg-slate-950 dark:text-slate-400"
                            >
                                Belum ada periode yang siap diajukan untuk
                                penutupan bulan.
                            </div>
                            <div v-else class="space-y-3">
                                <Link
                                    v-for="item in readyClosingPeriods"
                                    :key="`${item.year}-${item.month}`"
                                    :href="
                                        wasteManagementRoutes.faba.approvals.index.url(
                                            {
                                                query: {
                                                    year: item.year,
                                                },
                                            },
                                        )
                                    "
                                    class="group block rounded-xl px-3 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-blue-600 dark:hover:bg-slate-800"
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
                                                Saldo akhir
                                                {{
                                                    formatNumber(
                                                        item.closing_balance,
                                                    )
                                                }}
                                                ton dengan
                                                {{ item.warning_count }}
                                                peringatan aktif.
                                            </p>
                                        </div>
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700"
                                        >
                                            Siap diajukan
                                        </Badge>
                                    </div>
                                </Link>
                            </div>
                        </CardContent> </Card
                    ><Card
                        class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="px-5 py-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-orange-700/70 uppercase"
                            >
                                Antrian Persetujuan
                            </p>
                            <CardTitle class="text-lg tracking-tight">
                                Rekap menunggu pemeriksaan
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-5 pb-5">
                            <div
                                v-if="pendingApprovals.length === 0"
                                class="rounded-xl bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:bg-slate-950 dark:text-slate-400"
                            >
                                Tidak ada rekap yang menunggu pemeriksaan.
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
                                    class="group block rounded-xl px-3 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-blue-600 dark:hover:bg-slate-800"
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
                                                Menunggu keputusan final.
                                            </p>
                                        </div>
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full border border-orange-200 bg-orange-50 text-orange-700"
                                        >
                                            {{ formatFabaStatus(item.status) }}
                                        </Badge>
                                    </div>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
            <section
                aria-label="Rincian aktivitas dan penerima"
                class="grid items-start gap-4 xl:grid-cols-2"
            >
                <Card
                    class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
                >
                    <CardHeader class="px-5 py-4"
                        ><CardTitle class="text-base"
                            >Transaksi terbaru</CardTitle
                        >
                        <p class="text-xs text-slate-500">
                            Enam transaksi terakhir yang tersedia.
                        </p></CardHeader
                    >
                    <CardContent class="px-5 pb-5">
                        <p
                            v-if="!latestOperationalMovements.length"
                            class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-950"
                        >
                            Belum ada transaksi pada tahun ini.
                        </p>
                        <ul
                            v-else
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <li
                                v-for="item in latestOperationalMovements"
                                :key="item.id"
                                class="flex items-start justify-between gap-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold break-words text-slate-900 dark:text-white"
                                    >
                                        {{ item.display_number }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500"
                                    >
                                        {{
                                            formatFabaMovementType(
                                                item.movement_type,
                                            )
                                        }}
                                        ·
                                        {{
                                            formatFabaMaterial(
                                                item.material_type,
                                            )
                                        }}
                                        · {{ item.period_label }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ item.transaction_date
                                        }}<span v-if="item.created_by_user">
                                            ·
                                            {{
                                                item.created_by_user.name
                                            }}</span
                                        >
                                    </p>
                                </div>
                                <p
                                    class="shrink-0 text-right text-sm font-semibold text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{ formatNumber(item.quantity) }}
                                    <span class="font-normal text-slate-500">{{
                                        item.unit
                                    }}</span>
                                </p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card
                    class="min-w-0 gap-0 rounded-2xl border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
                >
                    <CardHeader class="px-5 py-4"
                        ><CardTitle class="text-base"
                            >Penerima pemanfaatan</CardTitle
                        >
                        <p class="text-xs text-slate-500">
                            Material yang dimanfaatkan penerima eksternal pada
                            tahun {{ year }}.
                        </p></CardHeader
                    >
                    <CardContent class="px-5 pb-5"
                        ><p
                            v-if="!topVendors.length"
                            class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-950"
                        >
                            Belum ada pemanfaatan oleh penerima eksternal pada
                            tahun ini.
                        </p>
                        <ul
                            v-else
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <li
                                v-for="item in topVendors"
                                :key="item.vendor_id ?? item.vendor_name"
                                class="flex items-baseline justify-between gap-4 py-3"
                            >
                                <p
                                    class="min-w-0 text-sm font-medium break-words text-slate-900 dark:text-white"
                                >
                                    {{ item.vendor_name }}
                                </p>
                                <p
                                    class="shrink-0 text-right text-lg font-semibold text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{ formatNumber(item.total_quantity) }}
                                    <span
                                        class="text-xs font-normal text-slate-500"
                                        >ton</span
                                    >
                                </p>
                            </li>
                        </ul></CardContent
                    >
                </Card>
            </section>
        </div>
    </WasteManagementLayout>
</template>
