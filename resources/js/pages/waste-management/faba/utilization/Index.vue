<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import type { FabaUtilizationEntry, FabaVendor } from '@/types/faba';

const props = defineProps<{
    entries: FabaUtilizationEntry[];
    vendors: FabaVendor[];
    filters: { materials: string[]; utilizationTypes: string[] };
}>();

const search = ref('');
const utilizationType = ref('all');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pemanfaatan FABA',
        href: wasteManagementRoutes.faba.utilization.index.url(),
    },
];

const filteredEntries = computed(() =>
    props.entries.filter((entry) => {
        const query = search.value.toLowerCase();

        return (
            (query.length === 0 ||
                entry.entry_number.toLowerCase().includes(query) ||
                entry.material_type.toLowerCase().includes(query) ||
                (entry.vendor?.name ?? '').toLowerCase().includes(query)) &&
            (utilizationType.value === 'all' ||
                entry.utilization_type === utilizationType.value)
        );
    }),
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Pemanfaatan FABA"
    >
        <Head title="Pemanfaatan FABA" />
        <div class="space-y-6 p-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <Heading
                    title="Pemanfaatan FABA"
                    description="Kelola transaksi pemanfaatan internal dan eksternal."
                />
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="
                            router.get(
                                wasteManagementRoutes.faba.utilization.export.csv(),
                            )
                        "
                    >
                        Ekspor CSV
                    </Button>
                    <Button
                        @click="
                            router.get(
                                wasteManagementRoutes.faba.utilization.create(),
                            )
                        "
                    >
                        Tambah pemanfaatan
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Input
                    v-model="search"
                    placeholder="Cari nomor / vendor / material"
                />
                <Select v-model="utilizationType">
                    <SelectTrigger
                        ><SelectValue placeholder="Semua tipe"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua tipe</SelectItem>
                        <SelectItem
                            v-for="item in filters.utilizationTypes"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div
                v-if="filteredEntries.length === 0"
                class="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada transaksi pemanfaatan yang cocok dengan filter saat ini.
            </div>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nomor</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>Material</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead>Vendor</TableHead>
                        <TableHead>Qty</TableHead>
                        <TableHead>Status Bulan</TableHead>
                        <TableHead>Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="entry in filteredEntries" :key="entry.id">
                        <TableCell>{{ entry.entry_number }}</TableCell>
                        <TableCell>{{ entry.transaction_date }}</TableCell>
                        <TableCell>{{ entry.material_type }}</TableCell>
                        <TableCell>{{ entry.utilization_type }}</TableCell>
                        <TableCell>{{ entry.vendor?.name || '-' }}</TableCell>
                        <TableCell
                            >{{ entry.quantity }} {{ entry.unit }}</TableCell
                        >
                        <TableCell class="capitalize">
                            {{ entry.approval_status.replace('_', ' ') }}
                        </TableCell>
                        <TableCell class="space-x-3">
                            <Link
                                :href="
                                    wasteManagementRoutes.faba.utilization.show(
                                        entry.id,
                                    ).url
                                "
                                >Detail</Link
                            >
                            <Link
                                :href="
                                    wasteManagementRoutes.faba.utilization.edit(
                                        entry.id,
                                    ).url
                                "
                                >Edit</Link
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </WasteManagementLayout>
</template>
