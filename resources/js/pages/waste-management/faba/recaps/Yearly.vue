<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

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
        totals: Record<string, number>;
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

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.recaps.yearly(), form);
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Rekap Tahunan FABA"
    >
        <Head title="Rekap Tahunan FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Rekap Tahunan FABA"
                description="Breakdown Januari sampai Desember."
            />
            <div class="flex gap-3">
                <Input v-model="form.year" type="number" class="max-w-40" />
                <Button @click="applyFilters">Terapkan</Button>
            </div>
            <Alert
                v-if="recap.months.some((month) => (month.warnings?.length ?? 0) > 0)"
                variant="destructive"
            >
                <AlertTitle>Peringatan tahunan</AlertTitle>
                <AlertDescription>
                    Ada periode yang masih memiliki warning operasional. Tinjau bulan terkait sebelum finalisasi laporan.
                </AlertDescription>
            </Alert>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Bulan</TableHead>
                        <TableHead>Produksi</TableHead>
                        <TableHead>Pemanfaatan</TableHead>
                        <TableHead>Saldo Akhir</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="month in recap.months" :key="month.month">
                        <TableCell>{{ month.period_label }}</TableCell>
                        <TableCell>{{ month.total_production }}</TableCell>
                        <TableCell>{{ month.total_utilization }}</TableCell>
                        <TableCell>{{ month.closing_balance }}</TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell class="font-semibold">Total</TableCell>
                        <TableCell>{{
                            recap.totals.total_production
                        }}</TableCell>
                        <TableCell>{{
                            recap.totals.total_utilization
                        }}</TableCell>
                        <TableCell>{{
                            recap.totals.closing_balance
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </WasteManagementLayout>
</template>
