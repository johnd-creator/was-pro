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
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaUtilizationMovement, FabaVendor } from '@/types/faba';

const props = defineProps<{
    entries: FabaUtilizationMovement[];
    vendors: FabaVendor[];
    initialMovementType?: string | null;
    filters: { materials: string[]; movementTypes: string[] };
}>();

const search = ref('');
const movementType = ref(props.initialMovementType ?? 'all');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title:
            movementType.value === 'utilization_internal'
                ? 'Pemanfaatan Internal'
                : movementType.value === 'utilization_external'
                  ? 'Pemanfaatan Eksternal'
                  : 'Pemanfaatan FABA',
        href: wasteManagementRoutes.faba.utilization.index.url(),
    },
];

const filteredEntries = computed(() =>
    props.entries.filter((entry) => {
        const query = search.value.toLowerCase();

        return (
            (query.length === 0 ||
                entry.display_number.toLowerCase().includes(query) ||
                entry.material_type.toLowerCase().includes(query) ||
                (entry.vendor?.name ?? '').toLowerCase().includes(query) ||
                (entry.internal_destination?.name ?? '').toLowerCase().includes(query)) &&
            (movementType.value === 'all' || entry.movement_type === movementType.value)
        );
    }),
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        :title="
            movementType === 'utilization_internal'
                ? 'Pemanfaatan Internal'
                : movementType === 'utilization_external'
                  ? 'Pemanfaatan Eksternal'
                  : 'Pemanfaatan FABA'
        "
    >
        <Head
            :title="
                movementType === 'utilization_internal'
                    ? 'Pemanfaatan Internal'
                    : movementType === 'utilization_external'
                      ? 'Pemanfaatan Eksternal'
                      : 'Pemanfaatan FABA'
            "
        />
        <div class="space-y-6 p-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <Heading
                    :title="
                        movementType === 'utilization_internal'
                            ? 'Pemanfaatan Internal'
                            : movementType === 'utilization_external'
                              ? 'Pemanfaatan Eksternal'
                              : 'Pemanfaatan FABA'
                    "
                    :description="
                        movementType === 'utilization_internal'
                            ? 'Kelola movement pemanfaatan untuk tujuan internal.'
                            : movementType === 'utilization_external'
                              ? 'Kelola movement pemanfaatan untuk vendor eksternal.'
                              : 'Kelola movement pemanfaatan internal dan eksternal.'
                    "
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
                                movementType !== 'all'
                                    ? wasteManagementRoutes.faba.utilization.create.url({ query: { movement_type: movementType } })
                                    : wasteManagementRoutes.faba.utilization.create(),
                            )
                        "
                    >
                        {{
                            movementType === 'utilization_internal'
                                ? 'Tambah internal'
                                : movementType === 'utilization_external'
                                  ? 'Tambah eksternal'
                                  : 'Tambah pemanfaatan'
                        }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Input
                    v-model="search"
                    placeholder="Cari nomor / vendor / material"
                />
                <Select v-model="movementType">
                    <SelectTrigger
                        ><SelectValue placeholder="Semua tipe"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua tipe</SelectItem>
                        <SelectItem
                            v-for="item in filters.movementTypes"
                            :key="item"
                            :value="item"
                        >
                            {{ formatFabaMovementType(item) }}
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
                        <TableCell>{{ entry.display_number }}</TableCell>
                        <TableCell>{{ formatFabaDate(entry.transaction_date) }}</TableCell>
                        <TableCell>{{ formatFabaMaterial(entry.material_type) }}</TableCell>
                        <TableCell>{{ formatFabaMovementType(entry.movement_type) }}</TableCell>
                        <TableCell>{{ entry.vendor?.name || entry.internal_destination?.name || '-' }}</TableCell>
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
