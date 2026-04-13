<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock3,
    Layers3,
    PackageSearch,
    Plus,
    RotateCcw,
    Search,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import CreateWasteHaulingForm from '@/components/waste-management/CreateWasteHaulingForm.vue';
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface HaulingItem {
    id: string;
    hauling_number: string;
    hauling_date: string;
    quantity: number;
    unit: string;
    status: string;
    status_label: string;
}

interface RecordItem {
    id: string;
    record_number: string;
    date: string;
    expiry_date: string | null;
    quantity: number;
    unit: string;
    source: string | null;
    approved_hauled_quantity: number;
    remaining_quantity: number;
    reserved_quantity: number;
    operational_status: 'not_hauled' | 'partially_hauled' | 'completed';
    operational_status_label: string;
    waste_type: {
        name: string | null;
        code: string | null;
        category: { name: string | null } | null;
    };
    hauling_history: HaulingItem[];
}

const props = defineProps<{
    records: RecordItem[];
    stats: {
        total_records: number;
        total_remaining_quantity: number;
        pending_requests: number;
    };
}>();

const search = ref('');
const urgencyFilter = ref<'all' | 'expired' | 'expiring_soon' | 'fresh'>('all');
const sortKey = ref<'priority' | 'latest' | 'remaining_high'>('priority');
const currentPage = ref(1);
const perPage = 10;

const isCreateDialogOpen = ref(false);
const selectedRecord = ref<RecordItem | null>(null);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pengangkutan Limbah',
        href: wasteManagementRoutes.haulings.index.url(),
    },
];

function openCreateDialog(record: RecordItem): void {
    selectedRecord.value = record;
    isCreateDialogOpen.value = true;
}

function handleCreateSuccess(): void {
    isCreateDialogOpen.value = false;
    selectedRecord.value = null;
    router.reload({ only: ['records', 'stats'] });
}

const filteredRecords = computed(() => {
    const query = search.value.trim().toLowerCase();

    return [...props.records]
        .filter((record) => {
            const matchesQuery =
                query.length === 0 ||
                record.record_number.toLowerCase().includes(query) ||
                record.waste_type.name?.toLowerCase().includes(query) ||
                record.waste_type.code?.toLowerCase().includes(query) ||
                record.waste_type.category?.name
                    ?.toLowerCase()
                    .includes(query) ||
                record.source?.toLowerCase().includes(query);

            if (!matchesQuery) {
                return false;
            }

            if (urgencyFilter.value === 'all') {
                return true;
            }

            return getExpiryStatus(record.expiry_date) === urgencyFilter.value;
        })
        .sort((left, right) => {
            if (sortKey.value === 'latest') {
                return (
                    new Date(right.date).getTime() -
                    new Date(left.date).getTime()
                );
            }

            if (sortKey.value === 'remaining_high') {
                return right.remaining_quantity - left.remaining_quantity;
            }

            const rankDifference =
                expiryRank(left.expiry_date) - expiryRank(right.expiry_date);

            if (rankDifference !== 0) {
                return rankDifference;
            }

            return (
                new Date(left.expiry_date ?? left.date).getTime() -
                new Date(right.expiry_date ?? right.date).getTime()
            );
        });
});

const expiredRecords = computed(() =>
    props.records.filter(
        (record) => getExpiryStatus(record.expiry_date) === 'expired',
    ),
);

const expiringSoonRecords = computed(() =>
    props.records.filter(
        (record) => getExpiryStatus(record.expiry_date) === 'expiring_soon',
    ),
);

const freshRecords = computed(() =>
    props.records.filter(
        (record) => getExpiryStatus(record.expiry_date) === 'fresh',
    ),
);

const attentionQuantity = computed(
    () =>
        expiredRecords.value.reduce(
            (sum, record) => sum + record.remaining_quantity,
            0,
        ) +
        expiringSoonRecords.value.reduce(
            (sum, record) => sum + record.remaining_quantity,
            0,
        ),
);

const highestPriorityRecord = computed(() => {
    const prioritized = [...props.records].sort((left, right) => {
        const leftRank = expiryRank(left.expiry_date);
        const rightRank = expiryRank(right.expiry_date);

        if (leftRank !== rightRank) {
            return leftRank - rightRank;
        }

        return (
            new Date(left.expiry_date ?? left.date).getTime() -
            new Date(right.expiry_date ?? right.date).getTime()
        );
    });

    return prioritized[0] ?? null;
});

const hasAnyRecords = computed(() => props.records.length > 0);
const hasActiveFilters = computed(
    () => search.value.trim().length > 0 || urgencyFilter.value !== 'all',
);
const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredRecords.value.length / perPage)),
);
const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * perPage;

    return filteredRecords.value.slice(start, start + perPage);
});
const pageRangeLabel = computed(() => {
    if (filteredRecords.value.length === 0) {
        return '0 dari 0 backlog';
    }

    const start = (currentPage.value - 1) * perPage + 1;
    const end = Math.min(start + perPage - 1, filteredRecords.value.length);

    return `${start}-${end} dari ${filteredRecords.value.length} backlog`;
});

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function getExpiryStatus(
    expiryDate: string | null,
): 'expired' | 'expiring_soon' | 'fresh' | 'na' {
    if (!expiryDate) {
        return 'na';
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const target = new Date(expiryDate);
    target.setHours(0, 0, 0, 0);

    const daysUntilExpiry = Math.ceil(
        (target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    if (daysUntilExpiry < 0) {
        return 'expired';
    }

    if (daysUntilExpiry <= 7) {
        return 'expiring_soon';
    }

    return 'fresh';
}

function expiryRank(expiryDate: string | null): number {
    const status = getExpiryStatus(expiryDate);

    if (status === 'expired') {
        return 0;
    }

    if (status === 'expiring_soon') {
        return 1;
    }

    if (status === 'fresh') {
        return 2;
    }

    return 3;
}

function operationalStatusClass(
    status: RecordItem['operational_status'],
): string {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (status === 'partially_hauled') {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-slate-100 text-slate-800';
}

function resetPage(): void {
    currentPage.value = 1;
}

function resetFilters(): void {
    search.value = '';
    urgencyFilter.value = 'all';
    sortKey.value = 'priority';
    resetPage();
}

function changePage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Pengangkutan Limbah"
    >
        <Head title="Pengangkutan Limbah" />

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
                                    Waste Logistics
                                </p>
                                <Heading
                                    title="Pengangkutan Limbah"
                                    description="Pantau backlog limbah siap angkut, prioritas masa simpan, dan progres penyelesaian operasional dari satu halaman."
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Backlog: {{ stats.total_records }}
                                </div>
                                <div
                                    class="wm-chip-amber px-3 py-1.5 text-xs font-medium"
                                >
                                    Menunggu approval:
                                    {{ stats.pending_requests }}
                                </div>
                                <div
                                    class="wm-chip-rose px-3 py-1.5 text-xs font-medium"
                                >
                                    Perlu atensi:
                                    {{
                                        expiredRecords.length +
                                        expiringSoonRecords.length
                                    }}
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
                                        Backlog siap angkut
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ stats.total_records }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ freshRecords.length }} backlog masih
                                        aman, sisanya butuh perhatian lebih
                                        dekat.
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
                                        Menunggu approval
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ stats.pending_requests }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        Pengajuan operator yang masih menunggu
                                        keputusan supervisor.
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
                                        Perlu atensi
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            expiredRecords.length +
                                            expiringSoonRecords.length
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ formatNumber(attentionQuantity) }}
                                        {{
                                            highestPriorityRecord?.unit ?? 'kg'
                                        }}
                                        berada di zona expired atau mendekati
                                        expired.
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
                                    Fokus Operasional
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Prioritas pengangkutan
                                </p>
                            </div>

                            <Button
                                as-child
                                variant="outline"
                                class="w-full justify-start border-white/90 bg-white/85 shadow-sm hover:bg-white dark:border-slate-800/80 dark:bg-slate-950/80 dark:hover:bg-slate-950"
                            >
                                <Link
                                    :href="
                                        wasteManagementRoutes.haulings.pendingApproval.url()
                                    "
                                >
                                    Review approval pengangkutan
                                </Link>
                            </Button>

                            <div
                                v-if="highestPriorityRecord"
                                class="wm-surface-subtle rounded-[22px] p-4"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Prioritas utama
                                </p>
                                <p
                                    class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{ highestPriorityRecord.record_number }}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        highestPriorityRecord.waste_type.name ||
                                        '-'
                                    }}
                                    • Sisa
                                    {{
                                        formatNumber(
                                            highestPriorityRecord.remaining_quantity,
                                        )
                                    }}
                                    {{ highestPriorityRecord.unit }}
                                </p>
                                <div class="mt-3">
                                    <ExpiryBadge
                                        :expiry-date="
                                            highestPriorityRecord.expiry_date
                                        "
                                    />
                                </div>
                                <Button
                                    size="sm"
                                    class="mt-4 w-full"
                                    @click="
                                        openCreateDialog(highestPriorityRecord)
                                    "
                                >
                                    <Truck class="mr-2 h-3.5 w-3.5" />
                                    Ajukan angkut prioritas
                                </Button>
                            </div>

                            <div
                                v-else
                                class="wm-surface-subtle rounded-[22px] p-4"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status backlog
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Belum ada backlog pengangkutan yang perlu
                                    ditindaklanjuti saat ini.
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
                                Cari dan saring backlog
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
                                v-model="search"
                                class="pl-9"
                                placeholder="Cari nomor catatan, jenis limbah, kategori, atau sumber"
                                @update:model-value="resetPage"
                            />
                        </div>

                        <Select
                            v-model="urgencyFilter"
                            @update:model-value="resetPage"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Filter urgensi" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Semua masa simpan</SelectItem
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
                                <SelectValue placeholder="Urutkan backlog" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="priority"
                                    >Prioritas tertinggi lebih dulu</SelectItem
                                >
                                <SelectItem value="latest"
                                    >Tanggal catatan terbaru</SelectItem
                                >
                                <SelectItem value="remaining_high"
                                    >Sisa terbesar ke terkecil</SelectItem
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
                                Daftar limbah siap angkut
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
                                    <TableHead>Catatan</TableHead>
                                    <TableHead>Jenis limbah</TableHead>
                                    <TableHead>Masa simpan</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Sudah diangkut</TableHead>
                                    <TableHead>Sisa</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="paginatedRecords.length === 0">
                                    <TableCell
                                        colspan="8"
                                        class="py-10 text-center"
                                    >
                                        <div
                                            class="flex flex-col items-center gap-3 text-muted-foreground"
                                        >
                                            <PackageSearch
                                                class="h-8 w-8 opacity-40"
                                            />
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                {{
                                                    hasAnyRecords
                                                        ? 'Tidak ada backlog yang cocok'
                                                        : 'Belum ada backlog pengangkutan'
                                                }}
                                            </p>
                                            <p class="max-w-md text-xs">
                                                {{
                                                    hasAnyRecords
                                                        ? 'Ubah pencarian atau filter urgensi untuk melihat backlog lain.'
                                                        : 'Limbah yang sudah disetujui dan masih tersisa akan muncul di sini.'
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
                                            </div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-for="record in paginatedRecords"
                                    :key="record.id"
                                    class="transition-colors hover:bg-slate-50/80 dark:bg-slate-900/70"
                                >
                                    <TableCell>
                                        <div class="font-mono text-xs">
                                            {{ record.record_number }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ formatDate(record.date) }}
                                            <span v-if="record.source">
                                                • {{ record.source }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="font-medium">
                                                {{
                                                    record.waste_type.name ||
                                                    '-'
                                                }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    record.waste_type.category
                                                        ?.name || '-'
                                                }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <ExpiryBadge
                                            :expiry-date="record.expiry_date"
                                            size="sm"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {{ formatNumber(record.quantity) }}
                                        {{ record.unit }}
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            formatNumber(
                                                record.approved_hauled_quantity,
                                            )
                                        }}
                                        {{ record.unit }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="space-y-1">
                                            <div>
                                                {{
                                                    formatNumber(
                                                        record.remaining_quantity,
                                                    )
                                                }}
                                                {{ record.unit }}
                                            </div>
                                            <div
                                                v-if="
                                                    record.reserved_quantity >
                                                    record.approved_hauled_quantity
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                Pending:
                                                {{
                                                    formatNumber(
                                                        record.reserved_quantity -
                                                            record.approved_hauled_quantity,
                                                    )
                                                }}
                                                {{ record.unit }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="
                                                operationalStatusClass(
                                                    record.operational_status,
                                                )
                                            "
                                        >
                                            {{
                                                record.operational_status_label
                                            }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                @click="
                                                    openCreateDialog(record)
                                                "
                                            >
                                                <Plus
                                                    class="mr-1.5 h-3.5 w-3.5"
                                                />
                                                Ajukan Angkut
                                            </Button>
                                            <Button
                                                as-child
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    :href="
                                                        wasteManagementRoutes.records.show(
                                                            record.id,
                                                        ).url
                                                    "
                                                >
                                                    Lihat Riwayat
                                                </Link>
                                            </Button>
                                        </div>
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
                        <span>
                            Halaman {{ currentPage }} dari
                            {{ totalPages }}
                        </span>
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

        <!-- Create Dialog -->
        <Dialog v-model:open="isCreateDialogOpen">
            <DialogContent
                class="max-h-[92vh] w-[min(96vw,1080px)] overflow-y-auto p-0 sm:max-w-none"
                :show-close-button="true"
            >
                <DialogHeader class="border-b px-6 pt-6 pb-4 sm:px-8">
                    <DialogTitle>Ajukan Pengangkutan Limbah</DialogTitle>
                </DialogHeader>
                <div class="px-6 py-5 sm:px-8 sm:py-6">
                    <CreateWasteHaulingForm
                        v-if="selectedRecord"
                        :record="selectedRecord"
                        @success="handleCreateSuccess"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </WasteManagementLayout>
</template>
