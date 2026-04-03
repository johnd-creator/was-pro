<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, BookMarked, Layers3, Scale } from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaMaterial,
    formatFabaMovementType,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    stockCard: {
        year: number;
        month: number | null;
        material_type: string | null;
        rows: Array<{
            id: string;
            transaction_date: string;
            display_number: string;
            material_type: string;
            movement_type: string;
            stock_effect: string;
            quantity: number;
            unit: string;
            vendor_name: string | null;
            internal_destination_name: string | null;
            purpose_name: string | null;
            running_balance: number;
            document_number: string | null;
        }>;
        summary: {
            count: number;
            latest_balances: Array<{ material_type: string; balance: number }>;
        };
    };
    filters: {
        year: number;
        month: number | null;
        material_type: string | null;
    };
    options: {
        materials: string[];
        months: Array<{ value: number; label: string }>;
    };
}>();

const ALL_MATERIALS = '__all_materials__';
const ALL_MONTHS = '__all_months__';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Stock Card FABA',
        href: wasteManagementRoutes.faba.recaps.stockCard.url(),
    },
];

const form = reactive({
    year: props.filters.year,
    month: props.filters.month ?? ALL_MONTHS,
    material_type: props.filters.material_type ?? ALL_MATERIALS,
});

const activeMonthLabel = computed(
    () =>
        props.options.months.find((month) => month.value === form.month)
            ?.label ?? 'Semua bulan',
);

const activeMaterialLabel = computed(() =>
    form.material_type === ALL_MATERIALS
        ? 'Semua material'
        : formatFabaMaterial(form.material_type),
);

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.recaps.stockCard(), {
        year: form.year,
        month: form.month === ALL_MONTHS ? null : form.month,
        material_type:
            form.material_type === ALL_MATERIALS ? null : form.material_type,
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Stock Card FABA"
    >
        <Head title="Stock Card FABA" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-12 left-1/4 -z-10 h-56 w-56 rounded-full bg-violet-200/18 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-20 right-0 -z-10 h-64 w-64 rounded-full bg-cyan-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-violet-50/20 dark:via-slate-900 dark:to-violet-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-violet-700/70 uppercase"
                                >
                                    Stock Ledger
                                </p>
                                <Heading
                                    title="Stock Card FABA"
                                    description="Pantau movement per material beserta saldo berjalan dengan layout audit yang lebih mudah discan."
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
                                                Movement
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ stockCard.summary.count }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-neutral"
                                        >
                                            <BookMarked class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="wm-hero-stat-card wm-hero-stat-violet"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-violet-700/80 uppercase"
                                            >
                                                Bulan
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ activeMonthLabel }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-violet"
                                        >
                                            <Scale class="h-5 w-5" />
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
                                                Material
                                            </p>
                                            <p
                                                class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ activeMaterialLabel }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-cyan"
                                        >
                                            <Layers3 class="h-5 w-5" />
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
                                    Filter Ledger
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Pilih snapshot stok
                                </p>
                            </div>

                            <div class="grid gap-4">
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
                                    <Select v-model="form.month">
                                        <SelectTrigger
                                            class="h-11 bg-white dark:bg-slate-950"
                                            ><SelectValue
                                                placeholder="Semua bulan"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="ALL_MONTHS"
                                                >Semua bulan</SelectItem
                                            >
                                            <SelectItem
                                                v-for="month in options.months"
                                                :key="month.value"
                                                :value="month.value"
                                            >
                                                {{ month.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
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
                                            <SelectItem :value="ALL_MATERIALS"
                                                >Semua material</SelectItem
                                            >
                                            <SelectItem
                                                v-for="material in options.materials"
                                                :key="material"
                                                :value="material"
                                            >
                                                {{
                                                    formatFabaMaterial(material)
                                                }}
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
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 xl:grid-cols-2">
                    <div class="wm-surface-elevated rounded-[26px] p-5">
                        <p
                            class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            Ringkasan
                        </p>
                        <div class="mt-4 space-y-3">
                            <div
                                class="wm-surface-subtle rounded-[20px] px-4 py-3 text-sm text-slate-700 dark:text-slate-200"
                            >
                                Total movement pada filter ini:
                                <span
                                    class="font-semibold text-slate-950 dark:text-slate-100"
                                    >{{ stockCard.summary.count }}</span
                                >
                            </div>
                            <div
                                v-for="item in stockCard.summary
                                    .latest_balances"
                                :key="item.material_type"
                                class="wm-surface-subtle rounded-[20px] px-4 py-3 text-sm text-slate-700 dark:text-slate-200"
                            >
                                {{ formatFabaMaterial(item.material_type) }}:
                                <span
                                    class="font-semibold text-slate-950 dark:text-slate-100"
                                    >{{ item.balance }} ton</span
                                >
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div
                        v-if="stockCard.rows.length === 0"
                        class="rounded-[26px] border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center text-sm text-slate-500 shadow-sm dark:text-slate-400"
                    >
                        Belum ada movement untuk filter stock card saat ini.
                    </div>

                    <div
                        v-else
                        class="wm-surface-elevated overflow-hidden rounded-[28px]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-5 py-4 dark:bg-slate-900/80"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Ledger Movement
                                </p>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Urutan transaksi dan saldo berjalan sesuai
                                    snapshot aktif.
                                </p>
                            </div>
                            <div
                                class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                {{ stockCard.rows.length }} baris
                            </div>
                        </div>

                        <Table>
                            <TableHeader
                                class="bg-slate-50/90 dark:bg-slate-900/80"
                            >
                                <TableRow
                                    class="border-slate-200/80 dark:border-slate-800/80"
                                >
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Nomor</TableHead>
                                    <TableHead>Material</TableHead>
                                    <TableHead>Tipe</TableHead>
                                    <TableHead>Qty</TableHead>
                                    <TableHead>Saldo Berjalan</TableHead>
                                    <TableHead>Referensi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in stockCard.rows"
                                    :key="row.id"
                                    class="border-slate-200/70 dark:border-slate-800/70"
                                >
                                    <TableCell>{{
                                        formatFabaDate(row.transaction_date)
                                    }}</TableCell>
                                    <TableCell
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                        >{{ row.display_number }}</TableCell
                                    >
                                    <TableCell>{{
                                        formatFabaMaterial(row.material_type)
                                    }}</TableCell>
                                    <TableCell>{{
                                        formatFabaMovementType(
                                            row.movement_type,
                                        )
                                    }}</TableCell>
                                    <TableCell
                                        >{{ row.stock_effect }}
                                        {{ row.quantity }}
                                        {{ row.unit }}</TableCell
                                    >
                                    <TableCell
                                        >{{ row.running_balance }}
                                        {{ row.unit }}</TableCell
                                    >
                                    <TableCell>{{
                                        row.vendor_name ||
                                        row.internal_destination_name ||
                                        row.purpose_name ||
                                        row.document_number ||
                                        '-'
                                    }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
