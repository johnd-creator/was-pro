<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ClipboardList,
    Database,
    Layers3,
    Scale
    
} from 'lucide-vue-next';
import type {LucideIcon} from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaDateTime,
    formatFabaMaterial,
    formatFabaMovementType,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaAuditLog,
    FabaClosingSnapshot,
    FabaMovement,
    FabaMonthlyRecap,
} from '@/types/faba';

interface SummaryCard {
    title: string;
    value: number;
    tone: 'blue' | 'emerald' | 'orange' | 'slate';
    icon: LucideIcon;
    hint: string;
}

const props = defineProps<{
    detail: {
        recap: FabaMonthlyRecap;
        snapshot?: FabaClosingSnapshot | null;
        movements: FabaMovement[];
        opening_balances: Array<{ material_type: string; quantity: number }>;
        vendor_breakdown: Array<{
            vendor_id: string | null;
            vendor_name: string;
            quantity: number;
        }>;
        internal_destination_breakdown: Array<{
            internal_destination_id: string | null;
            internal_destination_name: string;
            quantity: number;
        }>;
        purpose_breakdown: Array<{
            purpose_id: string | null;
            purpose_name: string;
            quantity: number;
        }>;
        audit_logs: FabaAuditLog[];
    };
    availablePeriods: Array<{
        year: number;
        month: number;
        period_label: string;
    }>;
    resolvedFromLatestPeriod: boolean;
    filters: { year: number; month: number };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Rekap Bulanan FABA',
        href: wasteManagementRoutes.faba.recaps.monthly.url(),
    },
];

const form = reactive({
    selectedPeriod: `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`,
});

const hasEntries = computed(() => props.detail.movements.length > 0);

const summaryCards = computed<SummaryCard[]>(() => [
    {
        title: 'Total Produksi',
        value: props.detail.recap.total_production,
        tone: 'blue',
        icon: Layers3,
        hint: 'Akumulasi produksi periode',
    },
    {
        title: 'Total Pemanfaatan',
        value: props.detail.recap.total_utilization,
        tone: 'emerald',
        icon: ClipboardList,
        hint: 'Akumulasi serapan periode',
    },
    {
        title: 'Saldo Awal',
        value: props.detail.recap.opening_balance,
        tone: 'slate',
        icon: Database,
        hint: 'Posisi sebelum movement',
    },
    {
        title: 'Saldo Akhir',
        value: props.detail.recap.closing_balance,
        tone: props.detail.recap.closing_balance < 0 ? 'orange' : 'slate',
        icon: Scale,
        hint:
            props.detail.recap.closing_balance < 0
                ? 'Perlu validasi saldo'
                : 'Posisi tutup periode',
    },
]);

const productionBreakdown = computed(() => [
    {
        label: 'Produksi Fly Ash',
        value: props.detail.recap.production_fly_ash,
    },
    {
        label: 'Produksi Bottom Ash',
        value: props.detail.recap.production_bottom_ash,
    },
]);

const utilizationBreakdown = computed(() => [
    {
        label: 'Pemanfaatan Fly Ash',
        value: props.detail.recap.utilization_fly_ash,
    },
    {
        label: 'Pemanfaatan Bottom Ash',
        value: props.detail.recap.utilization_bottom_ash,
    },
]);

const ledgerSummary = computed(() => [
    {
        label: 'Inflow Fly Ash',
        value: props.detail.recap.movement_summary?.inflow_fly_ash ?? 0,
    },
    {
        label: 'Outflow Fly Ash',
        value: props.detail.recap.movement_summary?.outflow_fly_ash ?? 0,
    },
    {
        label: 'Inflow Bottom Ash',
        value: props.detail.recap.movement_summary?.inflow_bottom_ash ?? 0,
    },
    {
        label: 'Outflow Bottom Ash',
        value: props.detail.recap.movement_summary?.outflow_bottom_ash ?? 0,
    },
]);

function applyFilters(): void {
    const [year, month] = form.selectedPeriod.split('-').map(Number);

    router.get(wasteManagementRoutes.faba.recaps.monthly(), { year, month });
}

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID');
}

function toneClasses(tone: SummaryCard['tone']): string {
    const map = {
        blue: 'border-sky-200/70 bg-linear-to-br from-white via-sky-50/70 to-blue-100/60 dark:border-sky-900/70 dark:from-slate-950 dark:via-sky-950/35 dark:to-blue-950/35',
        emerald:
            'border-emerald-200/70 bg-linear-to-br from-white via-emerald-50/70 to-teal-100/55 dark:border-emerald-900/70 dark:from-slate-950 dark:via-emerald-950/35 dark:to-teal-950/35',
        orange: 'border-orange-200/70 bg-linear-to-br from-white via-orange-50/75 to-amber-100/60 dark:border-orange-900/70 dark:from-slate-950 dark:via-orange-950/35 dark:to-amber-950/35',
        slate: 'border-slate-200/80 bg-linear-to-br from-white via-slate-50/80 to-slate-100/70 dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-slate-800/70',
    };

    return map[tone];
}

function iconWrapClasses(tone: SummaryCard['tone']): string {
    const map = {
        blue: 'bg-sky-600 text-white shadow-sky-500/20',
        emerald: 'bg-emerald-600 text-white shadow-emerald-500/20',
        orange: 'bg-orange-600 text-white shadow-orange-500/20',
        slate: 'bg-slate-700 text-white shadow-slate-500/20',
    };

    return map[tone];
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Rekap Bulanan FABA"
    >
        <Head title="Rekap Bulanan FABA" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[360px]"
            />
            <div
                class="pointer-events-none absolute -top-8 left-1/3 -z-10 h-56 w-56 rounded-full bg-blue-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-32 right-0 -z-10 h-56 w-56 rounded-full bg-orange-200/15 blur-3xl"
            />

            <div class="space-y-8 lg:space-y-10">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-blue-50/20 dark:via-slate-900 dark:to-blue-950/20"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase"
                                >
                                    FABA Monthly Recap
                                </p>
                                <Heading
                                    :title="`Rekap ${detail.recap.period_label}`"
                                    description="Baca produksi, pemanfaatan, saldo, dan histori pergerakan material dalam satu ringkasan bulanan."
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Movement: {{ detail.movements.length }}
                                </div>
                                <div
                                    class="wm-chip-blue px-3 py-1.5 text-xs font-medium"
                                >
                                    Warning: {{ detail.recap.warnings.length }}
                                </div>
                                <div
                                    class="wm-chip-emerald px-3 py-1.5 text-xs font-medium"
                                >
                                    Snapshot:
                                    {{
                                        detail.snapshot
                                            ? 'Tersedia'
                                            : 'Belum ada'
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    v-for="card in summaryCards"
                                    :key="card.title"
                                    :class="[
                                        'overflow-hidden rounded-[26px] border p-4 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_24px_50px_-28px_rgba(15,23,42,0.45)]',
                                        toneClasses(card.tone),
                                    ]"
                                >
                                    <div
                                        class="mb-4 flex items-start justify-between gap-3"
                                    >
                                        <div
                                            :class="[
                                                'rounded-2xl p-3 shadow-sm',
                                                iconWrapClasses(card.tone),
                                            ]"
                                        >
                                            <component
                                                :is="card.icon"
                                                class="size-4.5"
                                            />
                                        </div>
                                        <span
                                            class="rounded-full border border-white/90 bg-white/85 px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] text-slate-600 uppercase dark:text-slate-300"
                                        >
                                            ton
                                        </span>
                                    </div>
                                    <p
                                        class="text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100"
                                    >
                                        {{ card.title }}
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(card.value) }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ card.hint }}
                                    </p>
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
                                    Period Picker
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Ganti periode rekap
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label>Periode</Label>
                                <Select v-model="form.selectedPeriod">
                                    <SelectTrigger
                                        class="bg-white dark:bg-slate-950"
                                    >
                                        <SelectValue
                                            placeholder="Pilih periode rekap"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="period in availablePeriods"
                                            :key="`${period.year}-${period.month}`"
                                            :value="`${period.year}-${String(period.month).padStart(2, '0')}`"
                                        >
                                            {{ period.period_label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button class="w-full" @click="applyFilters">
                                Terapkan Periode
                            </Button>

                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Snapshot approval
                                </p>
                                <div
                                    v-if="detail.snapshot"
                                    class="mt-3 space-y-3"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full border border-blue-200 bg-white/90 text-blue-700 dark:bg-slate-950/85"
                                    >
                                        {{ detail.snapshot.status }}
                                    </Badge>
                                    <p
                                        class="text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        Disetujui pada
                                        {{
                                            formatFabaDateTime(
                                                detail.snapshot.approved_at,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        Oleh
                                        {{
                                            detail.snapshot.approved_by_user
                                                ?.name || '-'
                                        }}
                                    </p>
                                </div>
                                <p
                                    v-else
                                    class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada closing snapshot untuk periode
                                    ini.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <Alert
                    v-if="
                        resolvedFromLatestPeriod && availablePeriods.length > 0
                    "
                    class="border-blue-200 bg-blue-50 text-blue-900"
                >
                    <AlertTitle>Periode default disesuaikan</AlertTitle>
                    <AlertDescription>
                        Halaman otomatis menampilkan periode terakhir yang
                        memiliki transaksi FABA, yaitu
                        {{ detail.recap.period_label }}.
                    </AlertDescription>
                </Alert>

                <Alert
                    v-if="detail.recap.warnings.length > 0"
                    variant="destructive"
                >
                    <AlertTitle>Peringatan rekap</AlertTitle>
                    <AlertDescription>
                        <ul class="space-y-1">
                            <li
                                v-for="warning in detail.recap.warnings"
                                :key="warning.code"
                            >
                                {{ warning.message }}
                            </li>
                        </ul>
                    </AlertDescription>
                </Alert>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Balance Lens
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Produksi, pemanfaatan, dan saldo
                            </h3>
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-3">
                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-blue-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-blue-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase"
                                >
                                    Komposisi Produksi
                                </p>
                                <CardTitle class="text-lg tracking-tight">
                                    Komposisi produksi
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="item in productionBreakdown"
                                    :key="item.label"
                                    class="rounded-[22px] border border-white/90 bg-white/90 px-4 py-4 shadow-sm shadow-slate-100/70 dark:bg-slate-950/85"
                                >
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.value) }}
                                        <span
                                            class="ml-1 text-sm font-medium text-slate-400"
                                            >ton</span
                                        >
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-emerald-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                                >
                                    Komposisi Pemanfaatan
                                </p>
                                <CardTitle class="text-lg tracking-tight">
                                    Komposisi pemanfaatan
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="item in utilizationBreakdown"
                                    :key="item.label"
                                    class="rounded-[22px] border border-white/90 bg-white/90 px-4 py-4 shadow-sm shadow-slate-100/70 dark:bg-slate-950/85"
                                >
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.value) }}
                                        <span
                                            class="ml-1 text-sm font-medium text-slate-400"
                                            >ton</span
                                        >
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-slate-100/50 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-slate-800/70 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Ledger Summary
                                </p>
                                <CardTitle class="text-lg tracking-tight">
                                    Arus material
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="item in ledgerSummary"
                                    :key="item.label"
                                    class="flex items-center justify-between rounded-[18px] border border-white/90 bg-white/85 px-4 py-3 shadow-sm shadow-slate-100/60 dark:bg-slate-950/80"
                                >
                                    <p
                                        class="text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.value) }} ton
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <Card
                    v-if="!hasEntries"
                    class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-orange-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-orange-950/18 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                >
                    <CardContent
                        class="flex flex-col items-center gap-3 px-6 py-12 text-center"
                    >
                        <div
                            class="rounded-2xl bg-orange-100 p-3 text-orange-700"
                        >
                            <AlertTriangle class="size-5" />
                        </div>
                        <p
                            class="text-lg font-semibold text-slate-950 dark:text-slate-100"
                        >
                            Periode belum memiliki movement
                        </p>
                        <p
                            class="max-w-xl text-sm text-slate-500 dark:text-slate-400"
                        >
                            Belum ada movement FABA pada periode
                            {{ detail.recap.period_label }}.
                        </p>
                    </CardContent>
                </Card>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Supporting Detail
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Detail saldo, vendor, dan histori
                            </h3>
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-slate-100/50 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-slate-800/70 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader>
                                <CardTitle class="text-lg tracking-tight"
                                    >Opening Balance</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="item in detail.opening_balances"
                                    :key="item.material_type"
                                    class="flex items-center justify-between rounded-[18px] border border-white/90 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/60 dark:bg-slate-950/85"
                                >
                                    <p
                                        class="text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            formatFabaMaterial(
                                                item.material_type,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.quantity) }} ton
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-emerald-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader>
                                <CardTitle class="text-lg tracking-tight"
                                    >Vendor Breakdown</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <p
                                    v-if="detail.vendor_breakdown.length === 0"
                                    class="rounded-[18px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada pemanfaatan eksternal pada periode
                                    ini.
                                </p>
                                <div
                                    v-for="item in detail.vendor_breakdown"
                                    :key="item.vendor_id ?? item.vendor_name"
                                    class="flex items-center justify-between rounded-[18px] border border-white/90 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/60 dark:bg-slate-950/85"
                                >
                                    <p
                                        class="text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{ item.vendor_name }}
                                    </p>
                                    <p
                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.quantity) }} ton
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-blue-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-blue-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader>
                                <CardTitle class="text-lg tracking-tight"
                                    >Breakdown Internal</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <p
                                    v-if="
                                        detail.internal_destination_breakdown
                                            .length === 0
                                    "
                                    class="rounded-[18px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada pemanfaatan internal pada periode
                                    ini.
                                </p>
                                <div
                                    v-for="item in detail.internal_destination_breakdown"
                                    :key="
                                        item.internal_destination_id ??
                                        item.internal_destination_name
                                    "
                                    class="flex items-center justify-between rounded-[18px] border border-white/90 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/60 dark:bg-slate-950/85"
                                >
                                    <p
                                        class="text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{ item.internal_destination_name }}
                                    </p>
                                    <p
                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.quantity) }} ton
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-orange-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-orange-950/18 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader>
                                <CardTitle class="text-lg tracking-tight"
                                    >Purpose / Use-case</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <p
                                    v-if="detail.purpose_breakdown.length === 0"
                                    class="rounded-[18px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada purpose yang dicatat pada periode
                                    ini.
                                </p>
                                <div
                                    v-for="item in detail.purpose_breakdown"
                                    :key="item.purpose_id ?? item.purpose_name"
                                    class="flex items-center justify-between rounded-[18px] border border-white/90 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/60 dark:bg-slate-950/85"
                                >
                                    <p
                                        class="text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{ item.purpose_name }}
                                    </p>
                                    <p
                                        class="font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatNumber(item.quantity) }} ton
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-slate-100/50 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] xl:col-span-2 dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-slate-800/70 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader>
                                <CardTitle class="text-lg tracking-tight"
                                    >Stock Ledger</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <p
                                    v-if="detail.movements.length === 0"
                                    class="rounded-[18px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada movement ledger pada periode ini.
                                </p>
                                <div
                                    v-for="item in detail.movements"
                                    :key="item.id"
                                    class="grid gap-3 rounded-[20px] border border-white/90 bg-white/90 px-4 py-4 shadow-sm shadow-slate-100/60 md:grid-cols-[150px_minmax(0,1fr)_auto] dark:bg-slate-950/85"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                        >
                                            {{
                                                formatFabaDate(
                                                    item.transaction_date,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ item.display_number || item.id }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                        >
                                            {{
                                                formatFabaMaterial(
                                                    item.material_type,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                formatFabaMovementType(
                                                    item.movement_type,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                        >
                                            {{ item.stock_effect }}
                                            {{ formatNumber(item.quantity) }}
                                            {{ item.unit }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[30px] border-slate-200/70 bg-linear-to-br from-white via-slate-50/60 to-blue-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] xl:col-span-2 dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900 dark:to-blue-950/20 dark:shadow-[0_24px_60px_-36px_rgba(2,6,23,0.9)]"
                        >
                            <CardHeader>
                                <CardTitle class="text-lg tracking-tight"
                                    >Log Periode</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <p
                                    v-if="detail.audit_logs.length === 0"
                                    class="rounded-[18px] border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada audit trail pada periode ini.
                                </p>
                                <div
                                    v-for="item in detail.audit_logs"
                                    :key="item.id"
                                    class="rounded-[20px] border border-white/90 bg-white/90 px-4 py-4 shadow-sm shadow-slate-100/60 dark:bg-slate-950/85"
                                >
                                    <div
                                        class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                        >
                                            {{ item.actor?.name || 'Sistem' }}
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                formatFabaDateTime(
                                                    item.created_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <p
                                        class="mt-2 text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{ item.summary }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
