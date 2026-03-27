<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaDate, formatFabaMaterial, formatFabaMovementType } from '@/lib/faba';
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
    { title: 'Stock Card FABA', href: wasteManagementRoutes.faba.recaps.stockCard.url() },
];

const form = reactive({
    year: props.filters.year,
    month: props.filters.month ?? ALL_MONTHS,
    material_type: props.filters.material_type ?? ALL_MATERIALS,
});

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.recaps.stockCard(), {
        year: form.year,
        month: form.month === ALL_MONTHS ? null : form.month,
        material_type: form.material_type === ALL_MATERIALS ? null : form.material_type,
    });
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Stock Card FABA">
        <Head title="Stock Card FABA" />
        <div class="space-y-6 p-6">
            <Heading title="Stock Card / Stock Ledger" description="Lihat movement FABA dan saldo berjalan per material." />

            <div class="grid gap-4 md:grid-cols-4">
                <div class="grid gap-2">
                    <Label>Tahun</Label>
                    <input v-model="form.year" type="number" class="h-10 rounded-md border px-3 text-sm" />
                </div>
                <div class="grid gap-2">
                    <Label>Bulan</Label>
                    <Select v-model="form.month">
                        <SelectTrigger><SelectValue placeholder="Semua bulan" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_MONTHS">Semua bulan</SelectItem>
                            <SelectItem v-for="month in options.months" :key="month.value" :value="month.value">{{ month.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>Material</Label>
                    <Select v-model="form.material_type">
                        <SelectTrigger><SelectValue placeholder="Semua material" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_MATERIALS">Semua material</SelectItem>
                            <SelectItem v-for="material in options.materials" :key="material" :value="material">{{ formatFabaMaterial(material) }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-end">
                    <Button @click="applyFilters">Terapkan</Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Ringkasan</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div>Total movement: {{ stockCard.summary.count }}</div>
                        <div v-for="item in stockCard.summary.latest_balances" :key="item.material_type">
                            {{ formatFabaMaterial(item.material_type) }}: {{ item.balance }} ton
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
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
                    <TableRow v-for="row in stockCard.rows" :key="row.id">
                        <TableCell>{{ formatFabaDate(row.transaction_date) }}</TableCell>
                        <TableCell>{{ row.display_number }}</TableCell>
                        <TableCell>{{ formatFabaMaterial(row.material_type) }}</TableCell>
                        <TableCell>{{ formatFabaMovementType(row.movement_type) }}</TableCell>
                        <TableCell>{{ row.stock_effect }} {{ row.quantity }} {{ row.unit }}</TableCell>
                        <TableCell>{{ row.running_balance }} {{ row.unit }}</TableCell>
                        <TableCell>{{ row.vendor_name || row.internal_destination_name || row.purpose_name || row.document_number || '-' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </WasteManagementLayout>
</template>
