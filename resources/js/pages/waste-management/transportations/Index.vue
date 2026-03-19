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

        <div class="space-y-6 p-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <Heading
                    title="Transportasi Limbah"
                    description="Pantau pengiriman limbah, vendor, dan status transportasi dari satu halaman."
                />
                <div class="flex gap-2">
                    <Button
                        variant="outline"
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

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Menunggu</CardTitle
                        >
                        <Clock3 class="h-5 w-5 text-amber-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.pending }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Menunggu penjadwalan keberangkatan
                        </p>
                    </CardContent>
                </Card>

                <Card>
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
                        <p class="text-xs text-muted-foreground">
                            Sedang dalam proses pengiriman
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Terkirim</CardTitle
                        >
                        <CircleCheckBig class="h-5 w-5 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.delivered }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Sudah diterima tujuan
                        </p>
                    </CardContent>
                </Card>

                <Card>
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
                        <p class="text-xs text-muted-foreground">
                            Transportasi yang dibatalkan
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div
                class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2 xl:grid-cols-4"
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

                <Select v-model="statusFilter" @update:model-value="resetPage">
                    <SelectTrigger>
                        <SelectValue placeholder="Filter status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua status</SelectItem>
                        <SelectItem value="pending">Menunggu</SelectItem>
                        <SelectItem value="in_transit"
                            >Dalam perjalanan</SelectItem
                        >
                        <SelectItem value="delivered">Terkirim</SelectItem>
                        <SelectItem value="cancelled">Dibatalkan</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="sortKey" @update:model-value="resetPage">
                    <SelectTrigger>
                        <SelectValue placeholder="Urutkan transportasi" />
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

            <!-- Transportations Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
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
                        <TableRow v-if="paginatedTransportations.length === 0">
                            <TableCell :colspan="9" class="py-10 text-center">
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
                                            <RotateCcw class="mr-2 h-4 w-4" />
                                            Atur ulang filter
                                        </Button>
                                        <Button
                                            v-if="!hasAnyTransportations"
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
                        >
                            <TableCell class="font-mono text-xs">
                                {{ transportation.transportation_number }}
                            </TableCell>
                            <TableCell>{{
                                formatDate(transportation.transportation_date)
                            }}</TableCell>
                            <TableCell>
                                <div class="text-sm">
                                    <div class="font-medium">
                                        {{
                                            transportation.waste_record
                                                ?.record_number || '-'
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            transportation.waste_record
                                                ?.waste_type?.name || '-'
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
                                {{ transportation.vehicle_number || '-' }}
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
                    <span>Halaman {{ currentPage }} dari {{ totalPages }}</span>
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
    </WasteManagementLayout>
</template>
