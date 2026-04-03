<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Ban,
    CircleCheckBig,
    Clock3,
    DownloadIcon,
    RotateCcw,
    Search,
    Truck,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import TransportationStatusBadge from '@/components/waste-management/TransportationStatusBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface Vendor {
    id: string;
    name: string;
}

interface WasteRecord {
    id: string;
    record_number: string;
    waste_type?: {
        name: string;
    };
}

interface WasteTransportation {
    id: string;
    transportation_number: string;
    transportation_date: string;
    quantity: number;
    unit: string;
    status: 'pending' | 'in_transit' | 'delivered' | 'cancelled';
    vehicle_number?: string;
    driver_name?: string;
    waste_record?: WasteRecord;
    vendor?: Vendor;
    created_at: string;
}

interface Stats {
    pending: number;
    in_transit: number;
    delivered: number;
    cancelled: number;
}

type Props = {
    wasteTransportations: WasteTransportation[];
    stats: Stats;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Transportasi Limbah',
        href: '/waste-management/transportations',
    },
];

// Status filter
const statusFilter = ref<
    'all' | 'pending' | 'in_transit' | 'delivered' | 'cancelled'
>('all');
const searchQuery = ref('');
const sortKey = ref<'latest' | 'oldest' | 'status'>('latest');
const currentPage = ref(1);
const perPage = 10;
const hasAnyTransportations = computed(
    () => props.wasteTransportations.length > 0,
);
const hasActiveFilters = computed(
    () => searchQuery.value.trim().length > 0 || statusFilter.value !== 'all',
);

const filteredTransportations = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return [...props.wasteTransportations]
        .filter((transportation) => {
            const matchesStatus =
                statusFilter.value === 'all' ||
                transportation.status === statusFilter.value;

            const matchesQuery =
                query.length === 0 ||
                transportation.transportation_number
                    .toLowerCase()
                    .includes(query) ||
                transportation.waste_record?.record_number
                    ?.toLowerCase()
                    .includes(query) ||
                transportation.waste_record?.waste_type?.name
                    ?.toLowerCase()
                    .includes(query) ||
                transportation.vendor?.name?.toLowerCase().includes(query) ||
                transportation.driver_name?.toLowerCase().includes(query) ||
                transportation.vehicle_number?.toLowerCase().includes(query);

            return matchesStatus && matchesQuery;
        })
        .sort((left, right) => {
            if (sortKey.value === 'oldest') {
                return (
                    new Date(left.transportation_date).getTime() -
                    new Date(right.transportation_date).getTime()
                );
            }

            if (sortKey.value === 'status') {
                return left.status.localeCompare(right.status);
            }

            return (
                new Date(right.transportation_date).getTime() -
                new Date(left.transportation_date).getTime()
            );
        });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredTransportations.value.length / perPage)),
);

const paginatedTransportations = computed(() => {
    const start = (currentPage.value - 1) * perPage;

    return filteredTransportations.value.slice(start, start + perPage);
});

const pageRangeLabel = computed(() => {
    if (filteredTransportations.value.length === 0) {
        return '0 dari 0 transportasi';
    }

    const start = (currentPage.value - 1) * perPage + 1;
    const end = Math.min(
        start + perPage - 1,
        filteredTransportations.value.length,
    );

    return `${start}-${end} dari ${filteredTransportations.value.length} transportasi`;
});

function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function resetPage(): void {
    currentPage.value = 1;
}

function resetFilters(): void {
    searchQuery.value = '';
    statusFilter.value = 'all';
    sortKey.value = 'latest';
    resetPage();
}

function changePage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Transportasi Limbah"
    >
        <Head title="Transportasi Limbah - Manajemen Limbah" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-blue-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-blue-50/20 dark:via-slate-900 dark:to-blue-950/20"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase"
                                >
                                    Pusat Transportasi
                                </p>
                                <Heading
                                    title="Transportasi Limbah"
                                    description="Pantau pengiriman limbah, vendor, dan status transportasi dari satu halaman."
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Total pengiriman:
                                    {{ wasteTransportations.length }}
                                </div>
                                <div
                                    class="wm-chip-blue px-3 py-1.5 text-xs font-medium"
                                >
                                    Dalam perjalanan: {{ stats.in_transit }}
                                </div>
                                <div
                                    class="wm-chip-rose px-3 py-1.5 text-xs font-medium"
                                >
                                    Dibatalkan: {{ stats.cancelled }}
                                </div>
                            </div>

                            <div
                                class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                            >
                                <Card
                                    class="rounded-[26px] border-amber-200/70 bg-linear-to-br from-white via-amber-50/75 to-orange-100/60 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] dark:border-amber-900/70 dark:from-slate-950 dark:via-amber-950/35 dark:to-orange-950/35 dark:shadow-[0_18px_40px_-28px_rgba(2,6,23,0.9)]"
                                >
                                    <CardHeader
                                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                                    >
                                        <CardTitle class="text-sm font-medium"
                                            >Menunggu</CardTitle
                                        >
                                        <Clock3
                                            class="h-5 w-5 text-amber-600"
                                        />
                                    </CardHeader>
                                    <CardContent>
                                        <div class="text-2xl font-bold">
                                            {{ stats.pending }}
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Menunggu penjadwalan keberangkatan
                                        </p>
                                    </CardContent>
                                </Card>

                                <Card
                                    class="rounded-[26px] border-blue-200/70 bg-linear-to-br from-white via-blue-50/70 to-sky-100/60 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] dark:border-blue-900/70 dark:from-slate-950 dark:via-blue-950/35 dark:to-sky-950/35 dark:shadow-[0_18px_40px_-28px_rgba(2,6,23,0.9)]"
                                >
                                    <CardHeader
                                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                                    >
                                        <CardTitle class="text-sm font-medium"
                                            >Dalam Perjalanan</CardTitle
                                        >
                                        <Truck class="h-5 w-5 text-blue-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div class="text-2xl font-bold">
                                            {{ stats.in_transit }}
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Sedang dalam proses pengiriman
                                        </p>
                                    </CardContent>
                                </Card>

                                <Card
                                    class="rounded-[26px] border-emerald-200/70 bg-linear-to-br from-white via-emerald-50/70 to-teal-100/55 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] dark:border-emerald-900/70 dark:from-slate-950 dark:via-emerald-950/35 dark:to-teal-950/35 dark:shadow-[0_18px_40px_-28px_rgba(2,6,23,0.9)]"
                                >
                                    <CardHeader
                                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                                    >
                                        <CardTitle class="text-sm font-medium"
                                            >Terkirim</CardTitle
                                        >
                                        <CircleCheckBig
                                            class="h-5 w-5 text-green-600"
                                        />
                                    </CardHeader>
                                    <CardContent>
                                        <div class="text-2xl font-bold">
                                            {{ stats.delivered }}
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Sudah diterima tujuan
                                        </p>
                                    </CardContent>
                                </Card>

                                <Card
                                    class="rounded-[26px] border-rose-200/70 bg-linear-to-br from-white via-rose-50/70 to-red-100/60 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.35)] dark:border-rose-900/70 dark:from-slate-950 dark:via-rose-950/35 dark:to-red-950/35 dark:shadow-[0_18px_40px_-28px_rgba(2,6,23,0.9)]"
                                >
                                    <CardHeader
                                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                                    >
                                        <CardTitle class="text-sm font-medium"
                                            >Dibatalkan</CardTitle
                                        >
                                        <Ban class="h-5 w-5 text-rose-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div class="text-2xl font-bold">
                                            {{ stats.cancelled }}
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Transportasi yang dibatalkan
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-4 rounded-[28px] p-5"
                        >
                            <div class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Aksi Cepat
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Aksi cepat transportasi
                                </p>
                            </div>

                            <Button
                                variant="outline"
                                class="w-full justify-start border-white/90 bg-white/85 shadow-sm hover:bg-white dark:bg-slate-950 dark:bg-slate-950/80"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.transportations.export.csv(),
                                    )
                                "
                            >
                                <DownloadIcon class="mr-2 h-4 w-4" />
                                Ekspor CSV
                            </Button>
                            <Button
                                class="w-full justify-start"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.transportations.create(),
                                    )
                                "
                            >
                                Buat transportasi
                            </Button>

                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Fokus halaman
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Gunakan status, vendor, dan kendaraan
                                    sebagai jalur baca utama untuk mengontrol
                                    progres pengiriman.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Panel Filter
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Cari dan saring transportasi
                            </h3>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 rounded-[28px] border border-slate-200/80 bg-white/90 p-4 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.2)] md:grid-cols-2 xl:grid-cols-4 dark:bg-slate-950/85"
                    >
                        <div class="relative xl:col-span-2">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                class="pl-9"
                                placeholder="Cari nomor transportasi, vendor, catatan limbah, pengemudi, atau kendaraan"
                                @update:model-value="resetPage"
                            />
                        </div>

                        <Select
                            v-model="statusFilter"
                            @update:model-value="resetPage"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Filter status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Semua status</SelectItem
                                >
                                <SelectItem value="pending"
                                    >Menunggu</SelectItem
                                >
                                <SelectItem value="in_transit"
                                    >Dalam perjalanan</SelectItem
                                >
                                <SelectItem value="delivered"
                                    >Terkirim</SelectItem
                                >
                                <SelectItem value="cancelled"
                                    >Dibatalkan</SelectItem
                                >
                            </SelectContent>
                        </Select>

                        <Select
                            v-model="sortKey"
                            @update:model-value="resetPage"
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder="Urutkan transportasi"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="latest"
                                    >Terbaru lebih dulu</SelectItem
                                >
                                <SelectItem value="oldest"
                                    >Terlama lebih dulu</SelectItem
                                >
                                <SelectItem value="status">Status</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Tabel Aktif
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Daftar transportasi limbah
                            </h3>
                        </div>
                        <p
                            class="hidden text-sm text-slate-500 lg:block dark:text-slate-400"
                        >
                            {{ pageRangeLabel }}
                        </p>
                    </div>

                    <div
                        class="overflow-hidden rounded-[30px] border border-slate-200/80 bg-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:bg-slate-950/95"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow
                                    class="bg-slate-50/80 dark:bg-slate-900/70"
                                >
                                    <TableHead>No. transportasi</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Catatan limbah</TableHead>
                                    <TableHead>Vendor</TableHead>
                                    <TableHead>Jumlah</TableHead>
                                    <TableHead>Kendaraan</TableHead>
                                    <TableHead>Pengemudi</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-if="paginatedTransportations.length === 0"
                                >
                                    <TableCell
                                        :colspan="9"
                                        class="py-10 text-center"
                                    >
                                        <div
                                            class="flex flex-col items-center gap-3 text-muted-foreground"
                                        >
                                            <Truck class="h-8 w-8 opacity-40" />
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                {{
                                                    hasAnyTransportations
                                                        ? 'Tidak ada transportasi yang cocok'
                                                        : 'Belum ada transportasi limbah'
                                                }}
                                            </p>
                                            <p class="max-w-md text-xs">
                                                {{
                                                    hasAnyTransportations
                                                        ? 'Ubah pencarian atau filter status untuk melihat data transportasi lain.'
                                                        : 'Buat transportasi pertama untuk mulai melacak pengiriman limbah dan progres vendor.'
                                                }}
                                            </p>
                                            <div
                                                class="flex flex-wrap justify-center gap-2"
                                            >
                                                <Button
                                                    v-if="
                                                        hasAnyTransportations &&
                                                        hasActiveFilters
                                                    "
                                                    size="sm"
                                                    variant="outline"
                                                    @click="resetFilters"
                                                >
                                                    <RotateCcw
                                                        class="mr-2 h-4 w-4"
                                                    />
                                                    Atur ulang filter
                                                </Button>
                                                <Button
                                                    v-if="
                                                        !hasAnyTransportations
                                                    "
                                                    size="sm"
                                                    @click="
                                                        router.get(
                                                            wasteManagementRoutes.transportations.create(),
                                                        )
                                                    "
                                                >
                                                    Buat transportasi
                                                </Button>
                                            </div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-for="transportation in paginatedTransportations"
                                    :key="transportation.id"
                                    class="transition-colors hover:bg-slate-50/80 dark:bg-slate-900/70"
                                >
                                    <TableCell class="font-mono text-xs">
                                        {{
                                            transportation.transportation_number
                                        }}
                                    </TableCell>
                                    <TableCell>{{
                                        formatDate(
                                            transportation.transportation_date,
                                        )
                                    }}</TableCell>
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="font-medium">
                                                {{
                                                    transportation.waste_record
                                                        ?.record_number || '-'
                                                }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    transportation.waste_record
                                                        ?.waste_type?.name ||
                                                    '-'
                                                }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {{ transportation.vendor?.name || '-' }}
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            parseFloat(
                                                String(transportation.quantity),
                                            ).toFixed(2)
                                        }}
                                        {{ transportation.unit }}
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            transportation.vehicle_number || '-'
                                        }}
                                    </TableCell>
                                    <TableCell>
                                        {{ transportation.driver_name || '-' }}
                                    </TableCell>
                                    <TableCell>
                                        <TransportationStatusBadge
                                            :status="transportation.status"
                                            size="sm"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                router.get(
                                                    wasteManagementRoutes.transportations.show(
                                                        {
                                                            wasteTransportation:
                                                                transportation.id,
                                                        },
                                                    ),
                                                )
                                            "
                                        >
                                            Lihat
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <div
                    class="flex flex-col gap-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between"
                >
                    <p>{{ pageRangeLabel }}</p>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="currentPage === 1"
                            @click="changePage(currentPage - 1)"
                        >
                            Sebelumnya
                        </Button>
                        <span
                            >Halaman {{ currentPage }} dari
                            {{ totalPages }}</span
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="currentPage === totalPages"
                            @click="changePage(currentPage + 1)"
                        >
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </WasteManagementLayout>
</template>
