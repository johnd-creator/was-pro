<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatFabaDate, formatFabaMaterial, formatFabaMovementType, formatFabaStatus } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    entries: Array<{
        id: string;
        display_number: string;
        transaction_date: string;
        material_type: string;
        movement_type: string;
        quantity: number;
        unit: string;
        note: string | null;
        period_label: string;
        approval_status: string;
    }>;
    filters: { materials: string[]; movementTypes: string[] };
}>();

const search = ref('');
const material = ref('all');
const movementType = ref('all');

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Adjustment FABA', href: wasteManagementRoutes.faba.adjustments.index.url() },
];

const filteredEntries = computed(() =>
    props.entries.filter((entry) => {
        const query = search.value.toLowerCase();

        return (
            (query.length === 0 ||
                entry.display_number.toLowerCase().includes(query) ||
                entry.note?.toLowerCase().includes(query)) &&
            (material.value === 'all' || entry.material_type === material.value) &&
            (movementType.value === 'all' || entry.movement_type === movementType.value)
        );
    }),
);
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Adjustment FABA">
        <Head title="Adjustment FABA" />
        <div class="space-y-6 p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading title="Adjustment / Koreksi" description="Kelola koreksi stok FABA berbasis movement." />
                <Button @click="router.get(wasteManagementRoutes.faba.adjustments.create())">Tambah adjustment</Button>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Input v-model="search" placeholder="Cari nomor atau catatan" />
                <Select v-model="material">
                    <SelectTrigger><SelectValue placeholder="Semua material" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua material</SelectItem>
                        <SelectItem v-for="item in filters.materials" :key="item" :value="item">{{ formatFabaMaterial(item) }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="movementType">
                    <SelectTrigger><SelectValue placeholder="Semua tipe adjustment" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua tipe</SelectItem>
                        <SelectItem v-for="item in filters.movementTypes" :key="item" :value="item">{{ formatFabaMovementType(item) }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="filteredEntries.length === 0" class="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                Belum ada adjustment yang cocok dengan filter saat ini.
            </div>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nomor</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>Material</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead>Qty</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="entry in filteredEntries" :key="entry.id">
                        <TableCell>{{ entry.display_number }}</TableCell>
                        <TableCell>{{ formatFabaDate(entry.transaction_date) }}</TableCell>
                        <TableCell>{{ formatFabaMaterial(entry.material_type) }}</TableCell>
                        <TableCell>{{ formatFabaMovementType(entry.movement_type) }}</TableCell>
                        <TableCell>{{ entry.quantity }} {{ entry.unit }}</TableCell>
                        <TableCell><Badge variant="secondary">{{ formatFabaStatus(entry.approval_status) }}</Badge></TableCell>
                        <TableCell class="space-x-3">
                            <Link :href="wasteManagementRoutes.faba.adjustments.show(entry.id).url">Detail</Link>
                            <Link :href="wasteManagementRoutes.faba.adjustments.edit(entry.id).url">Edit</Link>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </WasteManagementLayout>
</template>
