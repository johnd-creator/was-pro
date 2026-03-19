<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
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
        months: Array<Record<string, number>>;
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
