<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
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

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.recaps.vendors(), {
        year: form.year,
        vendor_id: form.vendor_id === ALL_VENDORS ? null : form.vendor_id,
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Rekap Mitra FABA"
    >
        <Head title="Rekap Mitra FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Rekap per Mitra"
                description="Lihat total tonase dan transaksi vendor eksternal."
            />
            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label>Tahun</Label>
                    <Input v-model="form.year" type="number" />
                </div>
                <div class="grid gap-2">
                    <Label>Vendor</Label>
                    <Select v-model="form.vendor_id">
                        <SelectTrigger
                            ><SelectValue placeholder="Semua vendor"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_VENDORS"
                                >Semua vendor</SelectItem
                            >
                            <SelectItem
                                v-for="vendor in vendors"
                                :key="vendor.id"
                                :value="vendor.id"
                                >{{ vendor.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-end">
                    <Button @click="applyFilters">Terapkan</Button>
                </div>
            </div>
            <div
                v-if="recap.vendors.length === 0"
                class="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada data pemanfaatan eksternal untuk filter ini.
            </div>
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Vendor</TableHead>
                        <TableHead>Total Qty</TableHead>
                        <TableHead>Jumlah Transaksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="item in recap.vendors"
                        :key="item.vendor_id ?? item.vendor_name"
                    >
                        <TableCell>{{ item.vendor_name }}</TableCell>
                        <TableCell>{{ item.total_quantity }}</TableCell>
                        <TableCell>{{ item.transactions_count }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </WasteManagementLayout>
</template>
