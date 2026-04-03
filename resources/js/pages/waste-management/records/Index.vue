<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock3,
    DownloadIcon,
    FileText,
    Layers3,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
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
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import StatusBadge from '@/components/waste-management/StatusBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface WasteType {
    id: string;
    name: string;
    code: string;
    category?: {
        name: string;
        code: string;
    };
    characteristic?: {
        name: string;
        is_hazardous: boolean;
    };
}

interface WasteRecord {
    id: string;
    record_number: string;
    date: string;
    expiry_date: string | null;
    waste_type_id: string;
    quantity: number;
    unit: string;
    source: string | null;
    status: 'draft' | 'pending_review' | 'approved' | 'rejected';
    waste_type?: WasteType;
    submitted_by_user?: {
        name: string;
        email: string;
    };
    approved_by_user?: {
        name: string;
    };
    created_at: string;
    updated_at: string;
}

type Props = {
    wasteRecords: WasteRecord[];
};

const props = defineProps<Props>();

const expiryFilter = ref<'all' | 'expired' | 'expiring_soon' | 'fresh'>('all');
const searchQuery = ref('');
const sortKey = ref<'latest' | 'oldest' | 'quantity_high' | 'quantity_low'>(
    'latest',
);
const currentPage = ref(1);
const perPage = 10;
const hasAnyRecords = computed(() => props.wasteRecords.length > 0);
const hasActiveFilters = computed(
    () => searchQuery.value.trim().length > 0 || expiryFilter.value !== 'all',
);
const totalDraftRecords = computed(
    () =>
        props.wasteRecords.filter((record) => record.status === 'draft').length,
);
const totalPendingRecords = computed(
    () =>
        props.wasteRecords.filter(
            (record) => record.status === 'pending_review',
        ).length,
);
const totalExpiredRecords = computed(
    () =>
        props.wasteRecords.filter((record) => {
            if (!record.expiry_date) {
                return false;
            }

            const expiryDate = new Date(record.expiry_date);
            const today = new Date();
            expiryDate.setHours(0, 0, 0, 0);
            today.setHours(0, 0, 0, 0);

            return expiryDate.getTime() < today.getTime();
        }).length,
);

const filteredRecords = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const query = searchQuery.value.trim().toLowerCase();

    return [...props.wasteRecords]
        .filter((record) => {
            const matchesQuery =
                query.length === 0 ||
                record.record_number.toLowerCase().includes(query) ||
                record.waste_type?.name?.toLowerCase().includes(query) ||
                record.waste_type?.code?.toLowerCase().includes(query) ||
                record.waste_type?.category?.name
                    ?.toLowerCase()
                    .includes(query) ||
                record.source?.toLowerCase().includes(query);

            if (!matchesQuery) {
                return false;
            }

            if (expiryFilter.value === 'all') {
                return true;
            }

            if (!record.expiry_date) {
                return false;
            }

            const expiryDate = new Date(record.expiry_date);
            expiryDate.setHours(0, 0, 0, 0);
            const daysUntilExpiry = Math.ceil(
                (expiryDate.getTime() - today.getTime()) /
                    (1000 * 60 * 60 * 24),
            );

            switch (expiryFilter.value) {
                case 'expired':
                    return daysUntilExpiry < 0;
                case 'expiring_soon':
                    return daysUntilExpiry >= 0 && daysUntilExpiry <= 7;
                case 'fresh':
                    return daysUntilExpiry > 7;
                default:
                    return true;
            }
        })
        .sort((left, right) => {
            if (sortKey.value === 'oldest') {
                return (
                    new Date(left.date).getTime() -
                    new Date(right.date).getTime()
                );
            }

            if (sortKey.value === 'quantity_high') {
                return right.quantity - left.quantity;
            }

            if (sortKey.value === 'quantity_low') {
                return left.quantity - right.quantity;
            }

            return (
                new Date(right.date).getTime() - new Date(left.date).getTime()
            );
        });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredRecords.value.length / perPage)),
);

const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * perPage;

    return filteredRecords.value.slice(start, start + perPage);
});

const pageRangeLabel = computed(() => {
    if (filteredRecords.value.length === 0) {
        return '0 dari 0 catatan';
    }

    const start = (currentPage.value - 1) * perPage + 1;
    const end = Math.min(start + perPage - 1, filteredRecords.value.length);

    return `${start}-${end} dari ${filteredRecords.value.length} catatan`;
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Catatan Limbah',
        href: '/waste-management/records',
    },
];

function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getHazardousClass(isHazardous: boolean) {
    return isHazardous
        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
}

function resetPage(): void {
    currentPage.value = 1;
}

function resetFilters(): void {
    searchQuery.value = '';
    expiryFilter.value = 'all';
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
        title="Catatan Limbah"
    >
        <Head title="Catatan Limbah - Manajemen Limbah" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-teal-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-orange-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-teal-50/20 dark:via-slate-900 dark:to-teal-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-teal-700/70 uppercase"
                                >
                                    Waste Operations
                                </p>
                                <Heading
                                    title="Catatan Limbah"
                                    description="Pantau, cari, dan kelola seluruh catatan limbah dari satu halaman operasional."
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Total data: {{ wasteRecords.length }}
                                </div>
                                <div
                                    class="wm-chip-amber px-3 py-1.5 text-xs font-medium"
                                >
                                    Draft: {{ totalDraftRecords }}
                                </div>
                                <div
                                    class="wm-chip-rose px-3 py-1.5 text-xs font-medium"
                                >
                                    Risiko simpan: {{ totalExpiredRecords }}
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div
                                    class="wm-hero-record-card wm-hero-record-card-blue"
                                >
                                    <div
                                        class="mb-4 flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="rounded-2xl bg-sky-600 p-3 text-white shadow-sm shadow-sky-500/20"
                                        >
                                            <Layers3 class="size-4.5" />
                                        </div>
                                        <span
                                            class="wm-chip px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] uppercase"
                                        >
                                            Live
                                        </span>
                                    </div>
                                    <p
                                        class="text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100"
                                    >
                                        Total catatan
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ wasteRecords.length }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        Seluruh catatan limbah yang tercatat
                                        saat ini.
                                    </p>
                                </div>

                                <div
                                    class="wm-hero-record-card wm-hero-record-card-amber"
                                >
                                    <div
                                        class="mb-4 flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="rounded-2xl bg-amber-600 p-3 text-white shadow-sm shadow-amber-500/20"
                                        >
                                            <Clock3 class="size-4.5" />
                                        </div>
                                        <span
                                            class="wm-chip px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] uppercase"
                                        >
                                            Queue
                                        </span>
                                    </div>
                                    <p
                                        class="text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100"
                                    >
                                        Menunggu ditinjau
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ totalPendingRecords }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ totalDraftRecords }} masih dalam draf
                                        dan belum diajukan.
                                    </p>
                                </div>

                                <div
                                    class="wm-hero-record-card wm-hero-record-card-rose"
                                >
                                    <div
                                        class="mb-4 flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="rounded-2xl bg-rose-600 p-3 text-white shadow-sm shadow-rose-500/20"
                                        >
                                            <AlertTriangle class="size-4.5" />
                                        </div>
                                        <span
                                            class="wm-chip px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] uppercase"
                                        >
                                            Risk
                                        </span>
                                    </div>
                                    <p
                                        class="text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100"
                                    >
                                        Melewati batas simpan
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ totalExpiredRecords }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        Gunakan filter masa simpan untuk fokus
                                        pada catatan berisiko.
                                    </p>
                                </div>
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
                                    Aksi cepat operasional
                                </p>
                            </div>

                            <Button
                                variant="outline"
                                class="w-full justify-start border-white/90 bg-white/85 shadow-sm hover:bg-white dark:border-slate-800/80 dark:bg-slate-950/80 dark:hover:bg-slate-950"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.records.export.csv(),
                                    )
                                "
                            >
                                <DownloadIcon class="mr-2 h-4 w-4" />
                                Ekspor CSV
                            </Button>

                            <Button as-child class="w-full justify-start">
                                <Link
                                    :href="
                                        wasteManagementRoutes.records.create()
                                            .url
                                    "
                                >
                                    Tambah catatan
                                </Link>
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
                                    Gunakan pencarian, filter masa simpan, dan
                                    urutan data untuk mempercepat review catatan
                                    yang perlu tindakan.
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
                                Cari dan saring catatan
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
                                placeholder="Cari nomor catatan, jenis limbah, kategori, atau sumber"
                                @update:model-value="resetPage"
                            />
                        </div>

                        <Select
                            v-model="expiryFilter"
                            @update:model-value="resetPage"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Filter masa simpan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Semua status masa simpan</SelectItem
                                >
                                <SelectItem value="expired"
                                    >Sudah melewati batas</SelectItem
                                >
                                <SelectItem value="expiring_soon"
                                    >Segera kedaluwarsa</SelectItem
                                >
                                <SelectItem value="fresh"
                                    >Masih aman</SelectItem
                                >
                            </SelectContent>
                        </Select>

                        <Select
                            v-model="sortKey"
                            @update:model-value="resetPage"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Urutkan catatan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="latest"
                                    >Terbaru lebih dulu</SelectItem
                                >
                                <SelectItem value="oldest"
                                    >Terlama lebih dulu</SelectItem
                                >
                                <SelectItem value="quantity_high"
                                    >Jumlah terbesar ke terkecil</SelectItem
                                >
                                <SelectItem value="quantity_low"
                                    >Jumlah terkecil ke terbesar</SelectItem
                                >
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
                                Daftar catatan limbah
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
                                    <TableHead>Nomor catatan</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Jenis limbah</TableHead>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Jumlah</TableHead>
                                    <TableHead>Masa simpan</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="paginatedRecords.length === 0">
                                    <TableCell
                                        :colspan="9"
                                        class="py-10 text-center"
                                    >
                                        <div
                                            class="flex flex-col items-center gap-3 text-muted-foreground"
                                        >
                                            <FileText
                                                class="h-8 w-8 opacity-40"
                                            />
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                {{
                                                    hasAnyRecords
                                                        ? 'Tidak ada catatan yang cocok'
                                                        : 'Belum ada catatan limbah'
                                                }}
                                            </p>
                                            <p class="max-w-md text-xs">
                                                {{
                                                    hasAnyRecords
                                                        ? 'Ubah pencarian atau filter masa simpan untuk melihat catatan lain.'
                                                        : 'Mulai pencatatan limbah pertama agar proses pemantauan dan persetujuan bisa berjalan.'
                                                }}
                                            </p>
                                            <div
                                                class="flex flex-wrap justify-center gap-2"
                                            >
                                                <Button
                                                    v-if="
                                                        hasAnyRecords &&
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
                                                    v-if="!hasAnyRecords"
                                                    size="sm"
                                                    @click="
                                                        router.get(
                                                            wasteManagementRoutes.records.create(),
                                                        )
                                                    "
                                                >
                                                    Tambah catatan limbah
                                                </Button>
                                            </div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-for="record in paginatedRecords"
                                    :key="record.id"
                                    class="transition-colors hover:bg-slate-50/80 dark:bg-slate-900/70"
                                >
                                    <TableCell class="font-mono text-xs">
                                        {{ record.record_number }}
                                    </TableCell>
                                    <TableCell>{{
                                        formatDate(record.date)
                                    }}</TableCell>
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="font-medium">
                                                {{
                                                    record.waste_type?.name ||
                                                    '-'
                                                }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    record.waste_type?.code ||
                                                    '-'
                                                }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="space-y-1">
                                            <span
                                                v-if="
                                                    record.waste_type?.category
                                                "
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="
                                                    getHazardousClass(
                                                        !!record.waste_type
                                                            ?.characteristic
                                                            ?.is_hazardous,
                                                    )
                                                "
                                            >
                                                {{
                                                    record.waste_type.category
                                                        .name
                                                }}
                                            </span>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    record.waste_type
                                                        ?.characteristic
                                                        ?.is_hazardous
                                                        ? 'Karakteristik berbahaya'
                                                        : 'Karakteristik non-berbahaya'
                                                }}
                                            </p>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            parseFloat(
                                                String(record.quantity),
                                            ).toFixed(2)
                                        }}
                                        {{ record.unit }}
                                    </TableCell>
                                    <TableCell>
                                        <ExpiryBadge
                                            :expiry-date="record.expiry_date"
                                            size="sm"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            :status="record.status"
                                            size="sm"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    wasteManagementRoutes.records.show(
                                                        {
                                                            wasteRecord:
                                                                record.id,
                                                        },
                                                    ).url
                                                "
                                            >
                                                Lihat detail
                                            </Link>
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
