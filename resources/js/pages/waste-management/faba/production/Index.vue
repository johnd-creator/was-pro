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
import {
    formatFabaDate,
    formatFabaEntryType,
    formatFabaMaterial,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaProductionEntry } from '@/types/faba';

const props = defineProps<{
    entries: FabaProductionEntry[];
    filters: { materials: string[]; entryTypes: string[] };
}>();

const search = ref('');
const material = ref('all');
const entryType = ref('all');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Produksi FABA',
        href: wasteManagementRoutes.faba.production.index.url(),
    },
];

const filteredEntries = computed(() =>
    props.entries.filter((entry) => {
        const query = search.value.toLowerCase();

        return (
            (query.length === 0 ||
                entry.entry_number.toLowerCase().includes(query) ||
                entry.material_type.toLowerCase().includes(query) ||
                entry.entry_type.toLowerCase().includes(query)) &&
            (material.value === 'all' ||
                entry.material_type === material.value) &&
            (entryType.value === 'all' || entry.entry_type === entryType.value)
        );
    }),
);
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Produksi FABA">
        <Head title="Produksi FABA" />

        <div class="space-y-6 p-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <Heading
                    title="Produksi FABA"
                    description="Kelola transaksi produksi fly ash dan bottom ash."
                />
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="
                            router.get(
                                wasteManagementRoutes.faba.production.export.csv(),
                            )
                        "
                    >
                        Ekspor CSV
                    </Button>
                    <Button
                        @click="
                            router.get(wasteManagementRoutes.faba.production.create())
                        "
                    >
                        Tambah produksi
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Input
                    v-model="search"
                    placeholder="Cari nomor / material / tipe"
                />
                <Select v-model="material">
                    <SelectTrigger
                        ><SelectValue placeholder="Semua material"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua material</SelectItem>
                        <SelectItem
                            v-for="item in filters.materials"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="entryType">
                    <SelectTrigger
                        ><SelectValue placeholder="Semua tipe"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua tipe</SelectItem>
                        <SelectItem
                            v-for="item in filters.entryTypes"
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
                Belum ada transaksi produksi yang cocok dengan filter saat ini.
            </div>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nomor</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>Material</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead>Qty</TableHead>
                        <TableHead>Status Bulan</TableHead>
                        <TableHead>Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="entry in filteredEntries" :key="entry.id">
                        <TableCell>{{ entry.entry_number }}</TableCell>
                        <TableCell>{{ formatFabaDate(entry.transaction_date) }}</TableCell>
                        <TableCell>{{ formatFabaMaterial(entry.material_type) }}</TableCell>
                        <TableCell>{{ formatFabaEntryType(entry.entry_type) }}</TableCell>
                        <TableCell
                            >{{ entry.quantity }} {{ entry.unit }}</TableCell
                        >
                        <TableCell>
                            <Badge variant="secondary">
                                {{ formatFabaStatus(entry.approval_status) }}
                            </Badge>
                        </TableCell>
                        <TableCell class="space-x-3">
                            <Link
                                :href="
                                    wasteManagementRoutes.faba.production.show(
                                        entry.id,
                                    ).url
                                "
                                >Detail</Link
                            >
                            <Link
                                :href="
                                    wasteManagementRoutes.faba.production.edit(
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
