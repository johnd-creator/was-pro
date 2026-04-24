<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { DownloadIcon, Factory, Plus, Search } from 'lucide-vue-next';
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
        title: 'Pemanfaatan FABA',
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
                (entry.internal_destination?.name ?? '')
                    .toLowerCase()
                    .includes(query)) &&
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

const externalCount = computed(
    () =>
        filteredEntries.value.filter(
            (entry) => entry.movement_type === 'utilization_external',
        ).length,
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Pemanfaatan FABA"
    >
        <Head title="Pemanfaatan FABA" />
        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-amber-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-emerald-50/20 dark:via-slate-900 dark:to-emerald-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                                >
                                    Smart Utilization
                                </p>
                                <Heading
                                    title="Pemanfaatan FABA"
                                    description="Kelola pemanfaatan internal dan eksternal dari satu ledger operasional dengan filter mode sesuai kebutuhan review."
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
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
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
                                    class="wm-hero-stat-card wm-hero-stat-amber"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-amber-700/80 uppercase"
                                    >
                                        Eksternal
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ externalCount }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-3 rounded-[28px] p-5"
                        >
                            <Button
                                variant="outline"
                                class="w-full justify-between border-white/90 bg-white/85 shadow-sm dark:bg-slate-950/80"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.faba.utilization.export.csv(),
                                    )
                                "
                            >
                                Ekspor CSV
                                <DownloadIcon class="h-4 w-4" />
                            </Button>
                            <Button
                                class="w-full justify-between"
                                @click="
                                    router.get(
                                        movementType !== 'all'
                                            ? wasteManagementRoutes.faba.utilization.create.url(
                                                  {
                                                      query: {
                                                          movement_type:
                                                              movementType,
                                                      },
                                                  },
                                              )
                                            : wasteManagementRoutes.faba.utilization.create(),
                                    )
                                "
                            >
                                {{
                                    movementType === 'all'
                                        ? 'Tambah pemanfaatan'
                                        : `Tambah ${formatFabaMovementType(movementType).toLowerCase()}`
                                }}
                                <Plus class="h-4 w-4" />
                            </Button>
                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Lens
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Gunakan filter tipe untuk fokus ke jalur
                                    internal atau eksternal tanpa kehilangan
                                    ritme review.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div
                        class="grid gap-4 rounded-[28px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] md:grid-cols-2 dark:bg-slate-950/85"
                    >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <Input
                                v-model="search"
                                class="h-11 pl-9"
                                placeholder="Cari nomor / vendor / material"
                            />
                        </div>
                        <Select v-model="movementType">
                            <SelectTrigger class="h-11">
                                <SelectValue placeholder="Semua tipe" />
                            </SelectTrigger>
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
                        class="rounded-[26px] border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center text-sm text-slate-500 shadow-sm dark:text-slate-400"
                    >
                        Belum ada transaksi pemanfaatan yang cocok dengan filter
                        saat ini.
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
                                    Daftar Pemanfaatan
                                </p>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Snapshot movement pemanfaatan yang sedang
                                    tampil.
                                </p>
                            </div>
                            <div
                                class="rounded-full border border-slate-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                <Factory class="mr-1 inline h-3.5 w-3.5" />
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
                                    <TableHead>Vendor</TableHead>
                                    <TableHead>Qty</TableHead>
                                    <TableHead>Status Approval</TableHead>
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
                                    <TableCell>{{
                                        entry.vendor?.name ||
                                        entry.internal_destination?.name ||
                                        '-'
                                    }}</TableCell>
                                    <TableCell
                                        >{{ entry.quantity }}
                                        {{ entry.unit }}</TableCell
                                    >
                                    <TableCell>
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700 dark:text-slate-200"
                                        >
                                            {{
                                                formatFabaStatus(
                                                    entry.approval_status,
                                                )
                                            }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div
                                            class="flex flex-wrap gap-3 text-sm"
                                        >
                                            <Link
                                                :href="
                                                    wasteManagementRoutes.faba.utilization.show(
                                                        entry.id,
                                                    ).url
                                                "
                                                class="font-medium text-slate-900 underline-offset-4 hover:underline dark:text-slate-100"
                                                >Detail</Link
                                            >
                                            <Link
                                                v-if="entry.can_edit"
                                                :href="
                                                    wasteManagementRoutes.faba.utilization.edit(
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
