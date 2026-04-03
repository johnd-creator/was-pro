<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Wrench } from 'lucide-vue-next';
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
    {
        title: 'Adjustment FABA',
        href: wasteManagementRoutes.faba.adjustments.index.url(),
    },
];

const filteredEntries = computed(() =>
    props.entries.filter((entry) => {
        const query = search.value.toLowerCase();

        return (
            (query.length === 0 ||
                entry.display_number.toLowerCase().includes(query) ||
                entry.note?.toLowerCase().includes(query)) &&
            (material.value === 'all' ||
                entry.material_type === material.value) &&
            (movementType.value === 'all' ||
                entry.movement_type === movementType.value)
        );
    }),
);

const totalQuantity = computed(() =>
    filteredEntries.value.reduce(
        (total, entry) => total + Number(entry.quantity),
        0,
    ),
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Adjustment FABA"
    >
        <Head title="Adjustment FABA" />
        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-orange-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-slate-200/25 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-orange-50/20 dark:via-slate-900 dark:to-orange-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-orange-700/70 uppercase"
                                >
                                    Adjustment Desk
                                </p>
                                <Heading
                                    title="Adjustment / Koreksi"
                                    description="Kelola koreksi stok FABA berbasis movement dengan kontrol yang lebih mudah discan."
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Total Entry
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ filteredEntries.length }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-orange"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-orange-700/80 uppercase"
                                    >
                                        Volume
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ totalQuantity }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        ton
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-surface-subtle"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Material Aktif
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ filters.materials.length }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-3 rounded-[28px] p-5"
                        >
                            <Button
                                class="w-full justify-between"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.faba.adjustments.create(),
                                    )
                                "
                            >
                                Tambah adjustment
                                <Plus class="h-4 w-4" />
                            </Button>
                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Kontrol
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Adjustment sebaiknya dipakai untuk koreksi
                                    yang benar-benar perlu, lalu tetap
                                    ditelusuri lewat histori approval.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div
                        class="grid gap-4 rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] md:grid-cols-3 dark:bg-slate-950/85"
                    >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <Input
                                v-model="search"
                                class="h-11 pl-9"
                                placeholder="Cari nomor atau catatan"
                            />
                        </div>
                        <Select v-model="material">
                            <SelectTrigger class="h-11"
                                ><SelectValue placeholder="Semua material"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Semua material</SelectItem
                                >
                                <SelectItem
                                    v-for="item in filters.materials"
                                    :key="item"
                                    :value="item"
                                    >{{ formatFabaMaterial(item) }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <Select v-model="movementType">
                            <SelectTrigger class="h-11"
                                ><SelectValue
                                    placeholder="Semua tipe adjustment"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua tipe</SelectItem>
                                <SelectItem
                                    v-for="item in filters.movementTypes"
                                    :key="item"
                                    :value="item"
                                    >{{
                                        formatFabaMovementType(item)
                                    }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        v-if="filteredEntries.length === 0"
                        class="rounded-[26px] border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center text-sm text-slate-500 shadow-sm dark:text-slate-400"
                    >
                        Belum ada adjustment yang cocok dengan filter saat ini.
                    </div>

                    <div
                        v-else
                        class="wm-surface-elevated overflow-hidden rounded-[28px]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-5 py-4 dark:bg-slate-900/80"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Daftar Koreksi
                                </p>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Snapshot koreksi stock movement yang sedang
                                    tampil.
                                </p>
                            </div>
                            <div
                                class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                <Wrench class="mr-1 inline h-3.5 w-3.5" />
                                {{ filteredEntries.length }} entry
                            </div>
                        </div>

                        <Table>
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
                                <TableRow
                                    v-for="entry in filteredEntries"
                                    :key="entry.id"
                                >
                                    <TableCell
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                        >{{ entry.display_number }}</TableCell
                                    >
                                    <TableCell>{{
                                        formatFabaDate(entry.transaction_date)
                                    }}</TableCell>
                                    <TableCell>{{
                                        formatFabaMaterial(entry.material_type)
                                    }}</TableCell>
                                    <TableCell>{{
                                        formatFabaMovementType(
                                            entry.movement_type,
                                        )
                                    }}</TableCell>
                                    <TableCell
                                        >{{ entry.quantity }}
                                        {{ entry.unit }}</TableCell
                                    >
                                    <TableCell
                                        ><Badge
                                            variant="secondary"
                                            class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700 dark:text-slate-200"
                                            >{{
                                                formatFabaStatus(
                                                    entry.approval_status,
                                                )
                                            }}</Badge
                                        ></TableCell
                                    >
                                    <TableCell>
                                        <div
                                            class="flex flex-wrap gap-3 text-sm"
                                        >
                                            <Link
                                                :href="
                                                    wasteManagementRoutes.faba.adjustments.show(
                                                        entry.id,
                                                    ).url
                                                "
                                                class="font-medium text-slate-900 underline-offset-4 hover:underline dark:text-slate-100"
                                                >Detail</Link
                                            >
                                            <Link
                                                :href="
                                                    wasteManagementRoutes.faba.adjustments.edit(
                                                        entry.id,
                                                    ).url
                                                "
                                                class="text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline dark:text-slate-100"
                                                >Edit</Link
                                            >
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
