<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    formatFabaEntryType,
    formatFabaMaterial,
    formatFabaUtilizationType,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaMonthlyRecap, FabaVendor } from '@/types/faba';

const ALL_MATERIALS = '__all_materials__';
const ALL_ENTRY_TYPES = '__all_entry_types__';
const ALL_UTILIZATION_TYPES = '__all_utilization_types__';
const ALL_VENDORS = '__all_vendors__';

const props = defineProps<{
    currentYear: number;
    filters: {
        year: number;
        month: number;
        material_type: string | null;
        entry_type: string | null;
        utilization_type: string | null;
        vendor_id: string | null;
    };
    availablePeriods: Array<{ year: number; month: number; period_label: string }>;
    resolvedFromLatestPeriod: boolean;
    options: {
        materials: string[];
        entryTypes: string[];
        utilizationTypes: string[];
        vendors: FabaVendor[];
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
}>();

const form = reactive({
    year: props.filters.year,
    month: props.filters.month,
    material_type: props.filters.material_type ?? ALL_MATERIALS,
    entry_type: props.filters.entry_type ?? ALL_ENTRY_TYPES,
    utilization_type: props.filters.utilization_type ?? ALL_UTILIZATION_TYPES,
    vendor_id: props.filters.vendor_id ?? ALL_VENDORS,
});

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Laporan FABA', href: wasteManagementRoutes.faba.reports.index.url() },
];

const normalizedFilters = computed(() => ({
    year: form.year,
    month: form.month,
    material_type: form.material_type === ALL_MATERIALS ? null : form.material_type,
    entry_type: form.entry_type === ALL_ENTRY_TYPES ? null : form.entry_type,
    utilization_type: form.utilization_type === ALL_UTILIZATION_TYPES ? null : form.utilization_type,
    vendor_id: form.vendor_id === ALL_VENDORS ? null : form.vendor_id,
}));

function download(url: string): void {
    window.location.assign(url);
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Laporan FABA">
        <Head title="Laporan FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Laporan FABA"
                description="Ekspor laporan yang konsisten dengan rekap bulanan, tahunan, dan transaksi operasional."
            />

            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label>Tahun</Label>
                    <Input v-model="form.year" type="number" />
                </div>
                <div class="grid gap-2">
                    <Label>Bulan</Label>
                    <Input v-model="form.month" type="number" min="1" max="12" />
                </div>
                <div class="grid gap-2">
                    <Label>Material</Label>
                    <Select v-model="form.material_type">
                        <SelectTrigger><SelectValue placeholder="Semua material" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_MATERIALS">Semua material</SelectItem>
                            <SelectItem v-for="item in options.materials" :key="item" :value="item">
                                {{ formatFabaMaterial(item) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>Tipe Produksi</Label>
                    <Select v-model="form.entry_type">
                        <SelectTrigger><SelectValue placeholder="Semua tipe produksi" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_ENTRY_TYPES">Semua tipe produksi</SelectItem>
                            <SelectItem v-for="item in options.entryTypes" :key="item" :value="item">
                                {{ formatFabaEntryType(item) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>Tipe Pemanfaatan</Label>
                    <Select v-model="form.utilization_type">
                        <SelectTrigger><SelectValue placeholder="Semua tipe pemanfaatan" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_UTILIZATION_TYPES">Semua tipe pemanfaatan</SelectItem>
                            <SelectItem v-for="item in options.utilizationTypes" :key="item" :value="item">
                                {{ formatFabaUtilizationType(item) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>Vendor Eksternal</Label>
                    <Select v-model="form.vendor_id">
                        <SelectTrigger><SelectValue placeholder="Semua vendor" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_VENDORS">Semua vendor</SelectItem>
                            <SelectItem v-for="vendor in options.vendors" :key="vendor.id" :value="vendor.id">
                                {{ vendor.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Rekap Bulanan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2 text-sm md:grid-cols-2">
                            <p>Periode: {{ monthlyRecap.period_label }}</p>
                            <p>Total produksi: {{ monthlyRecap.total_production }}</p>
                            <p>Total pemanfaatan: {{ monthlyRecap.total_utilization }}</p>
                            <p>Saldo akhir: {{ monthlyRecap.closing_balance }}</p>
                        </div>
                        <p
                            v-if="monthlyRecap.warnings.length > 0"
                            class="text-sm text-amber-700"
                        >
                            {{ monthlyRecap.warnings.length }} warning akan ikut dijelaskan pada export bulanan.
                        </p>
                        <Button
                            @click="
                                download(
                                    wasteManagementRoutes.faba.reports.monthly.csv({
                                        query: { year: normalizedFilters.year, month: normalizedFilters.month },
                                    }).url,
                                )
                            "
                        >
                            Ekspor Rekap Bulanan
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rekap Tahunan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2 text-sm md:grid-cols-2">
                            <p>Tahun: {{ yearlyRecap.year }}</p>
                            <p>Total produksi: {{ yearlyRecap.totals.total_production }}</p>
                            <p>Total pemanfaatan: {{ yearlyRecap.totals.total_utilization }}</p>
                            <p>Saldo akhir: {{ yearlyRecap.totals.closing_balance }}</p>
                        </div>
                        <p
                            v-if="yearlyRecap.latest_month"
                            class="text-sm text-muted-foreground"
                        >
                            Snapshot periode terpilih: {{ yearlyRecap.latest_month.period_label }}
                        </p>
                        <Button
                            @click="
                                download(
                                    wasteManagementRoutes.faba.reports.yearly.csv({
                                        query: { year: normalizedFilters.year },
                                    }).url,
                                )
                            "
                        >
                            Ekspor Rekap Tahunan
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Transaksi Produksi</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Export produksi mengikuti filter tahun, bulan, material, dan tipe produksi yang aktif.
                        </p>
                        <Button
                            variant="outline"
                            @click="
                                download(
                                    wasteManagementRoutes.faba.production.export.csv({
                                        query: {
                                            year: normalizedFilters.year,
                                            month: normalizedFilters.month,
                                            material_type: normalizedFilters.material_type,
                                            entry_type: normalizedFilters.entry_type,
                                        },
                                    }).url,
                                )
                            "
                        >
                            Ekspor Transaksi Produksi
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Transaksi Pemanfaatan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Export pemanfaatan mengikuti filter tahun, bulan, material, tipe pemanfaatan, dan vendor eksternal.
                        </p>
                        <Button
                            variant="outline"
                            @click="
                                download(
                                    wasteManagementRoutes.faba.utilization.export.csv({
                                        query: {
                                            year: normalizedFilters.year,
                                            month: normalizedFilters.month,
                                            material_type: normalizedFilters.material_type,
                                            utilization_type: normalizedFilters.utilization_type,
                                            vendor_id: normalizedFilters.vendor_id,
                                        },
                                    }).url,
                                )
                            "
                        >
                            Ekspor Transaksi Pemanfaatan
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
