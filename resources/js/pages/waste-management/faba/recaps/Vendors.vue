<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, Building2, History, Users2 } from 'lucide-vue-next';
import { computed, reactive } from 'vue';
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
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaDate, formatFabaMaterial } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaVendor } from '@/types/faba';

const props = defineProps<{
    recap: {
        year: number;
        vendors: Array<{
            vendor_id: string | null;
            vendor_name: string;
            total_quantity: number;
            transactions_count: number;
            materials: string[];
            history: Array<{
                id: string;
                transaction_date: string;
                display_number: string;
                material_type: string;
                quantity: number;
                unit: string;
            }>;
        }>;
    };
    vendors: FabaVendor[];
    filters: { year: number; vendor_id: string | null };
}>();

const ALL_VENDORS = '__all__';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Rekap Mitra FABA',
        href: wasteManagementRoutes.faba.recaps.vendors.url(),
    },
];

const form = reactive({
    year: props.filters.year,
    vendor_id: props.filters.vendor_id ?? ALL_VENDORS,
});

const selectedVendor = computed(() =>
    form.vendor_id === ALL_VENDORS
        ? null
        : props.vendors.find((item) => item.id === form.vendor_id)?.name,
);

function applyFilters(): void {
    router.get(scopedUrl(wasteManagementRoutes.faba.recaps.vendors()), {
        year: form.year,
        vendor_id: form.vendor_id === ALL_VENDORS ? null : form.vendor_id,
    });
}
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Rekap Mitra FABA"
    >
        <Head title="Rekap Mitra FABA" />

        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="space-y-4">
                <section class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="space-y-4">
                            <div class="space-y-3">
                                <Heading title="Rekap per Mitra" />
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
                                                Vendor Aktif
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ recap.vendors.length }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-neutral"
                                        >
                                            <Users2 class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="wm-hero-stat-card wm-hero-stat-blue"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-blue-700/80 uppercase"
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
                                            class="wm-hero-icon wm-hero-icon-blue"
                                        >
                                            <Building2 class="h-5 w-5" />
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
                                                Fokus Vendor
                                            </p>
                                            <p
                                                class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{
                                                    selectedVendor ??
                                                    'Semua vendor'
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-emerald"
                                        >
                                            <History class="h-5 w-5" />
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
                                    Filter Vendor
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Pilih horizon audit
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
                                    <Label>Vendor</Label>
                                    <Select v-model="form.vendor_id">
                                        <SelectTrigger
                                            class="h-11 bg-white dark:bg-slate-950"
                                            ><SelectValue
                                                placeholder="Semua vendor"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="ALL_VENDORS"
                                                >Semua vendor</SelectItem
                                            >
                                            <SelectItem
                                                v-for="vendor in vendors"
                                                :key="vendor.id"
                                                :value="vendor.id"
                                            >
                                                {{ vendor.name }}
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

                <section
                    v-if="recap.vendors.length === 0"
                    class="rounded-[26px] border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center text-sm text-slate-500 shadow-sm dark:text-slate-400"
                >
                    Belum ada data pemanfaatan eksternal untuk filter ini.
                </section>

                <section v-else class="space-y-6">
                    <div
                        class="wm-surface-elevated overflow-hidden rounded-[28px]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-5 py-4 dark:bg-slate-900/80"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Ringkasan Vendor
                                </p>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Tonase dan jumlah transaksi vendor pada
                                    tahun yang dipilih.
                                </p>
                            </div>
                            <div
                                class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                {{ recap.vendors.length }} vendor
                            </div>
                        </div>

                        <Table>
                            <TableHeader
                                class="bg-slate-50/90 dark:bg-slate-900/80"
                            >
                                <TableRow
                                    class="border-slate-200/80 dark:border-slate-800/80"
                                >
                                    <TableHead>Vendor</TableHead>
                                    <TableHead>Material</TableHead>
                                    <TableHead>Total Qty</TableHead>
                                    <TableHead>Jumlah Transaksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="item in recap.vendors"
                                    :key="item.vendor_id ?? item.vendor_name"
                                    class="border-slate-200/70 dark:border-slate-800/70"
                                >
                                    <TableCell
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                        >{{ item.vendor_name }}</TableCell
                                    >
                                    <TableCell>{{
                                        item.materials
                                            .map((material) =>
                                                formatFabaMaterial(material),
                                            )
                                            .join(', ')
                                    }}</TableCell>
                                    <TableCell>{{
                                        item.total_quantity
                                    }}</TableCell>
                                    <TableCell>{{
                                        item.transactions_count
                                    }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div
                        v-if="
                            filters.vendor_id &&
                            recap.vendors[0]?.history?.length
                        "
                        class="rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                    >
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Riwayat Vendor
                                </p>
                                <h2
                                    class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    {{ recap.vendors[0].vendor_name }}
                                </h2>
                            </div>
                            <div
                                class="rounded-full border border-slate-200/80 bg-slate-50/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                {{ recap.vendors[0].history.length }} transaksi
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3">
                            <article
                                v-for="item in recap.vendors[0].history"
                                :key="item.id"
                                class="rounded-[22px] border border-slate-200/80 bg-slate-50/85 px-4 py-4 dark:bg-slate-900/75"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                        >
                                            {{ item.display_number }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                formatFabaDate(
                                                    item.transaction_date,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="text-sm text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            formatFabaMaterial(
                                                item.material_type,
                                            )
                                        }}
                                        • {{ item.quantity }} {{ item.unit }}
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
