<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatFabaMaterial, formatFabaMovementType } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaAnomalyItem, FabaMonthlyRecap, FabaPurpose, FabaStockCardRow, FabaVendor } from '@/types/faba';

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
    availablePeriods: Array<{ year: number; month: number; period_label: string }>;
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
    { title: 'Laporan FABA', href: wasteManagementRoutes.faba.reports.index.url() },
];

const normalizedFilters = computed(() => ({
    year: form.year,
    month: form.month,
    material_type: form.material_type === ALL ? null : form.material_type,
    movement_type: form.movement_type === ALL ? null : form.movement_type,
    vendor_id: form.vendor_id === ALL ? null : form.vendor_id,
    internal_destination_id: form.internal_destination_id === ALL ? null : form.internal_destination_id,
    purpose_id: form.purpose_id === ALL ? null : form.purpose_id,
}));

function download(url: string): void {
    window.location.assign(url);
}

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.reports.index(), normalizedFilters.value);
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Laporan FABA">
        <Head title="Laporan FABA" />

        <div class="space-y-6 p-6">
            <Heading
                title="Laporan FABA"
                description="Ekspor laporan movement, rekap closing, stock card, dan anomaly secara konsisten."
            />

            <div
                v-if="resolvedFromLatestPeriod"
                class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
            >
                Periode default diarahkan ke periode terakhir yang memiliki data movement.
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Filter Laporan</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                    <div class="grid gap-2">
                        <Label>Tahun</Label>
                        <Input v-model="form.year" type="number" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Bulan</Label>
                        <Input v-model="form.month" type="number" min="1" max="12" />
                        <p class="text-xs text-muted-foreground">
                            Periode tersedia:
                            {{
                                availablePeriods
                                    .filter((item) => item.year === form.year)
                                    .map((item) => item.period_label)
                                    .join(', ') || '-'
                            }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label>Material</Label>
                        <Select v-model="form.material_type">
                            <SelectTrigger><SelectValue placeholder="Semua material" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">Semua material</SelectItem>
                                <SelectItem v-for="item in options.materials" :key="item" :value="item">
                                    {{ formatFabaMaterial(item) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Movement Type</Label>
                        <Select v-model="form.movement_type">
                            <SelectTrigger><SelectValue placeholder="Semua movement" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">Semua movement</SelectItem>
                                <SelectItem v-for="item in options.movementTypes" :key="item" :value="item">
                                    {{ formatFabaMovementType(item) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Vendor</Label>
                        <Select v-model="form.vendor_id">
                            <SelectTrigger><SelectValue placeholder="Semua vendor" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">Semua vendor</SelectItem>
                                <SelectItem v-for="vendor in options.vendors" :key="vendor.id" :value="vendor.id">
                                    {{ vendor.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Tujuan Internal</Label>
                        <Select v-model="form.internal_destination_id">
                            <SelectTrigger><SelectValue placeholder="Semua tujuan internal" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">Semua tujuan internal</SelectItem>
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
                        <Label>Purpose / Use-case</Label>
                        <Select v-model="form.purpose_id">
                            <SelectTrigger><SelectValue placeholder="Semua purpose" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">Semua purpose</SelectItem>
                                <SelectItem v-for="purpose in options.purposes" :key="purpose.id" :value="purpose.id">
                                    {{ purpose.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" @click="applyFilters">Terapkan Filter</Button>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Rekap Closing Bulanan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3 text-sm sm:grid-cols-2">
                            <p>Periode: {{ monthlyRecap.period_label }}</p>
                            <p>Total produksi: {{ monthlyRecap.total_production }}</p>
                            <p>Total pemanfaatan: {{ monthlyRecap.total_utilization }}</p>
                            <p>Saldo akhir: {{ monthlyRecap.closing_balance }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-for="warning in monthlyRecap.warnings" :key="warning.code" variant="secondary">
                                {{ warning.message }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.monthly.xlsx.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                month: normalizedFilters.month,
                                            },
                                        }),
                                    )
                                "
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.monthly.pdf.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                month: normalizedFilters.month,
                                            },
                                        }),
                                    )
                                "
                            >
                                PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rekap Tahunan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3 text-sm sm:grid-cols-2">
                            <p>Tahun: {{ yearlyRecap.year }}</p>
                            <p>Total produksi: {{ yearlyRecap.totals.total_production }}</p>
                            <p>Total pemanfaatan: {{ yearlyRecap.totals.total_utilization }}</p>
                            <p>Saldo akhir: {{ yearlyRecap.totals.closing_balance }}</p>
                        </div>
                        <p v-if="yearlyRecap.latest_month" class="text-sm text-muted-foreground">
                            Snapshot terbaru: {{ yearlyRecap.latest_month.period_label }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.yearly.xlsx.url({
                                            query: { year: normalizedFilters.year },
                                        }),
                                    )
                                "
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.yearly.pdf.url({
                                            query: { year: normalizedFilters.year },
                                        }),
                                    )
                                "
                            >
                                PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Vendor Eksternal</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ vendorRecap.vendors.length }} vendor pada filter tahun aktif.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.vendors.xlsx.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                vendor_id: normalizedFilters.vendor_id,
                                            },
                                        }),
                                    )
                                "
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.vendors.pdf.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                vendor_id: normalizedFilters.vendor_id,
                                            },
                                        }),
                                    )
                                "
                            >
                                PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tujuan Internal</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ internalDestinationRecap.destinations.length }} tujuan internal pada filter tahun aktif.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.internalDestinations.xlsx.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                internal_destination_id: normalizedFilters.internal_destination_id,
                                            },
                                        }),
                                    )
                                "
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.internalDestinations.pdf.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                internal_destination_id: normalizedFilters.internal_destination_id,
                                            },
                                        }),
                                    )
                                "
                            >
                                PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Purpose / Use-case</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ purposeRecap.purposes.length }} purpose pada filter tahun aktif.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.purposes.xlsx.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                purpose_id: normalizedFilters.purpose_id,
                                            },
                                        }),
                                    )
                                "
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.purposes.pdf.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                purpose_id: normalizedFilters.purpose_id,
                                            },
                                        }),
                                    )
                                "
                            >
                                PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Stock Card</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3 text-sm sm:grid-cols-2">
                            <p>Total movement: {{ stockCard.summary.count }}</p>
                            <p>
                                Saldo aktif:
                                {{
                                    stockCard.summary.latest_balances
                                        .map((item) => `${formatFabaMaterial(item.material_type)} ${item.balance}`)
                                        .join(' | ') || '-'
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.stockCard.xlsx.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                month: normalizedFilters.month,
                                                material_type: normalizedFilters.material_type,
                                            },
                                        }),
                                    )
                                "
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.stockCard.pdf.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                month: normalizedFilters.month,
                                                material_type: normalizedFilters.material_type,
                                            },
                                        }),
                                    )
                                "
                            >
                                PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Anomaly Report</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ anomalyReport.items.length }} anomaly / warning ditemukan untuk filter periode aktif.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.anomalies.xlsx.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                month: normalizedFilters.month,
                                            },
                                        }),
                                    )
                                "
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    download(
                                        wasteManagementRoutes.faba.reports.anomalies.pdf.url({
                                            query: {
                                                year: normalizedFilters.year,
                                                month: normalizedFilters.month,
                                            },
                                        }),
                                    )
                                "
                            >
                                PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
