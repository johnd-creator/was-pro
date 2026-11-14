<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock3,
    DownloadIcon,
    FileText,
    Layers3,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue';
import Link from '@/components/OrganizationLink.vue';
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
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import CreateWasteRecordForm from '@/components/waste-management/CreateWasteRecordForm.vue';
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import StatusBadge from '@/components/waste-management/StatusBadge.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    changeRecordListState,
    parseRecordListState,
    recordContractHeaders,
    recordListPage,
    recordListUrl,
} from '@/lib/record-list';
import type { RecordPaginator } from '@/lib/record-list';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface WasteType {
    id: string;
    name: string;
    code: string;
    storage_period_days: number;
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
    source_name?: string | null;
    status: 'draft' | 'pending_review' | 'approved' | 'rejected';
    approved_hauled_quantity: number;
    remaining_quantity: number;
    has_simulated_hauling: boolean;
    reserved_quantity: number;
    operational_status: 'not_hauled' | 'partially_hauled' | 'completed';
    operational_status_label: string;
    waste_type?: WasteType;
    submitted_by_user?: {
        name: string;
        email: string;
    };
    approved_by_user?: {
        name: string;
    };
    created_by?: { name: string } | null;
    created_at: string;
    updated_at: string;
}

type Props = {
    filters: {
        month: string;
        attention: string | null;
        as_of: string;
        search?: string;
        sort?: 'latest' | 'oldest' | 'quantity_high' | 'quantity_low';
    };
    recordSummary: {
        total: number;
        draft: number;
        pending: number;
        expired: number;
        expiring_soon: number;
    };
    wasteRecords: WasteRecord[] | RecordPaginator<WasteRecord>;
    wasteTypes?: WasteType[];
    wasteSources?: { id: string; name: string; is_active: boolean }[];
};

const { download, scopedUrl } = useOrganizationContext();

const props = defineProps<Props>();

const isCreateDialogOpen = ref(false);

const page = usePage();
const committedState = computed(() =>
    parseRecordListState(page.url, props.filters),
);
const expiryFilter = computed(() => committedState.value.attention);
const searchQuery = ref(committedState.value.search);
const sortKey = ref(committedState.value.sort);
const recordsPage = computed(() =>
    recordListPage(props.wasteRecords, committedState.value),
);
const currentPage = computed(() => recordsPage.value.current_page);
const isLoading = ref(false);
const hasAnyRecords = computed(() => props.recordSummary.total > 0);
const hasActiveFilters = computed(
    () => searchQuery.value.trim().length > 0 || expiryFilter.value !== 'all',
);
const totalDraftRecords = computed(() => props.recordSummary.draft);
const totalPendingRecords = computed(() => props.recordSummary.pending);
const totalExpiredRecords = computed(() => props.recordSummary.expired);
const totalExpiringSoonRecords = computed(
    () => props.recordSummary.expiring_soon,
);
const attentionLabels: Record<string, string> = {
    all: 'Semua catatan',
    expired: 'Melewati batas simpan',
    expiring_soon: 'Mendekati batas simpan',
    critical: 'Risiko masa simpan',
    backlog: 'Belum terangkut / masih bersisa',
    pending: 'Menunggu ditinjau pada periode ini',
    fresh: 'Masih aman',
    completed: 'Selesai diangkut',
};
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let cancelVisit: (() => void) | undefined;
let visitGeneration = 0;
let ownVisit = false;
function cancelPending(): void {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = undefined;
    visitGeneration++;
    const cancel = cancelVisit;
    cancelVisit = undefined;
    cancel?.();
    isLoading.value = false;
}
function syncControls(): void {
    searchQuery.value = committedState.value.search;
    sortKey.value = committedState.value.sort;
}
watch(() => [page.url, props.filters, props.wasteRecords], syncControls);
const removeBeforeListener = router.on('before', () => {
    if (!ownVisit) cancelPending();
});
const removeNavigateListener = router.on('navigate', () => {
    cancelVisit = undefined;
    cancelPending();
    syncControls();
});
onMounted(() => window.addEventListener('popstate', cancelPending));
function navigate(targetPage = 1, attention = expiryFilter.value): void {
    cancelPending();
    const generation = visitGeneration;
    const state = changeRecordListState(committedState.value, {
        search: searchQuery.value,
        sort: sortKey.value,
        attention,
        page: targetPage,
    });
    isLoading.value = true;
    ownVisit = true;
    router.get(
        scopedUrl(
            recordListUrl(wasteManagementRoutes.records.index.url(), state),
        ),
        {},
        {
            headers: recordContractHeaders,
            preserveScroll: true,
            preserveState: true,
            onCancelToken: (token) => {
                cancelVisit = () => token.cancel();
            },
            onFinish: () => {
                if (generation === visitGeneration) {
                    cancelVisit = undefined;
                    isLoading.value = false;
                }
            },
        },
    );
    ownVisit = false;
}
function changeAttention(value: unknown): void {
    if (typeof value !== 'string' || !(value in attentionLabels)) return;
    navigate(1, value);
}
function scheduleSearch(): void {
    cancelPending();
    isLoading.value = true;
    searchTimer = setTimeout(() => navigate(), 300);
}
onBeforeUnmount(() => {
    cancelPending();
    removeBeforeListener();
    removeNavigateListener();
    window.removeEventListener('popstate', cancelPending);
});
const paginatedRecords = computed(() => recordsPage.value.data);
const totalPages = computed(() => Math.max(1, recordsPage.value.last_page));
const pageRangeLabel = computed(() =>
    recordsPage.value.total === 0
        ? '0 dari 0 catatan'
        : `${recordsPage.value.from}-${recordsPage.value.to} dari ${recordsPage.value.total} catatan`,
);
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Catatan Limbah',
        href: wasteManagementRoutes.records.index.url(),
    },
];

function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getOperationalStatusClass(status: WasteRecord['operational_status']) {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
    }

    if (status === 'partially_hauled') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    }

    return 'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-200';
}

function exportCsv(): void {
    download(
        wasteManagementRoutes.records.export.csv.url({
            query: {
                search: searchQuery.value.trim(),
                month: committedState.value.month,
                attention:
                    expiryFilter.value === 'all' ? null : expiryFilter.value,
                sort: sortKey.value,
            },
        }),
    );
}

function resetFilters(): void {
    if (searchTimer) clearTimeout(searchTimer);
    searchQuery.value = '';
    sortKey.value = 'latest';
    navigate(1, 'all');
}
function changePage(page: number): void {
    navigate(Math.min(Math.max(page, 1), totalPages.value));
}
function handleCreateSuccess(): void {
    isCreateDialogOpen.value = false;
    navigate(currentPage.value);
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Catatan Limbah"
    >
        <Head title="Catatan Limbah - Manajemen Limbah" />

        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="space-y-4">
                <section class="space-y-4">
                    <div class="min-w-0">
                        <div class="space-y-4">
                            <div class="space-y-3">
                                <Heading title="Catatan Limbah" />
                            </div>

                            <div
                                class="flex flex-wrap items-center gap-2"
                                aria-label="Tindakan catatan limbah"
                            >
                                <Button
                                    v-if="props.wasteTypes"
                                    size="sm"
                                    @click="isCreateDialogOpen = true"
                                >
                                    <Plus class="mr-2 size-4" />Tambah catatan
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="exportCsv"
                                >
                                    <DownloadIcon class="mr-2 size-4" />Ekspor
                                    CSV
                                </Button>
                                <Button
                                    v-if="expiryFilter !== 'all'"
                                    variant="ghost"
                                    size="sm"
                                    @click="changeAttention('all')"
                                    >Lihat semua catatan</Button
                                >
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Total data: {{ recordSummary.total }}
                                </div>
                                <div
                                    class="wm-chip-amber px-3 py-1.5 text-xs font-medium"
                                >
                                    Draft: {{ totalDraftRecords }}
                                </div>
                                <div
                                    class="wm-chip-rose px-3 py-1.5 text-xs font-medium"
                                >
                                    Risiko simpan:
                                    {{
                                        totalExpiredRecords +
                                        totalExpiringSoonRecords
                                    }}
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div
                                    class="wm-hero-record-card wm-hero-record-card-blue"
                                >
                                    <div
                                        class="mb-2 flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="rounded-lg bg-sky-600 p-2 text-white shadow-sm shadow-sky-500/20"
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
                                        class="mt-1 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ recordSummary.total }}
                                    </p>
                                    <p
                                        class="mt-1 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        Catatan tercatat sampai
                                        {{ filters.as_of }}.
                                    </p>
                                </div>

                                <div
                                    class="wm-hero-record-card wm-hero-record-card-amber"
                                >
                                    <div
                                        class="mb-2 flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="rounded-lg bg-amber-600 p-2 text-white shadow-sm shadow-amber-500/20"
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
                                        class="mt-1 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ totalPendingRecords }}
                                    </p>
                                    <p
                                        class="mt-1 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ totalPendingRecords }} catatan sudah
                                        diajukan dan menunggu tinjauan. Draf
                                        ditampilkan terpisah.
                                    </p>
                                </div>

                                <div
                                    class="wm-hero-record-card wm-hero-record-card-rose"
                                >
                                    <div
                                        class="mb-2 flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="rounded-lg bg-rose-600 p-2 text-white shadow-sm shadow-rose-500/20"
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
                                        class="mt-1 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ totalExpiredRecords }}
                                    </p>
                                    <p
                                        class="mt-1 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ totalExpiringSoonRecords }} catatan
                                        mendekati batas simpan dalam 7 hari.
                                    </p>
                                    <button
                                        v-if="totalExpiringSoonRecords > 0"
                                        type="button"
                                        class="mt-2 text-xs font-semibold text-amber-700 underline underline-offset-4 dark:text-amber-300"
                                        @click="
                                            changeAttention('expiring_soon')
                                        "
                                    >
                                        Lihat yang mendekati batas
                                    </button>
                                    <p class="sr-only">
                                        Gunakan filter masa simpan untuk
                                        meninjau catatan
                                    </p>
                                </div>
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
                                placeholder="Cari nomor catatan, jenis limbah, atau sumber"
                                @update:model-value="scheduleSearch"
                            />
                        </div>

                        <Select
                            :model-value="expiryFilter"
                            @update:model-value="changeAttention"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Filter catatan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pending"
                                    >Menunggu ditinjau</SelectItem
                                >
                                <SelectItem value="backlog"
                                    >Belum terangkut / masih bersisa</SelectItem
                                >
                                <SelectItem
                                    v-if="expiryFilter === 'critical'"
                                    value="critical"
                                    >Risiko masa simpan</SelectItem
                                >
                                <SelectItem value="all"
                                    >Semua catatan</SelectItem
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
                                <SelectItem value="completed"
                                    >Selesai / stok nol</SelectItem
                                >
                            </SelectContent>
                        </Select>

                        <Select
                            v-model="sortKey"
                            @update:model-value="() => navigate()"
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
                        <p
                            v-if="isLoading"
                            role="status"
                            aria-live="polite"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            Memuat catatan...
                        </p>
                        <Table :aria-busy="isLoading">
                            <TableHeader>
                                <TableRow
                                    class="bg-slate-50/80 dark:bg-slate-900/70"
                                >
                                    <TableHead>Nomor catatan</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Dicatat oleh</TableHead>
                                    <TableHead>Jenis limbah</TableHead>
                                    <TableHead>Sumber limbah</TableHead>
                                    <TableHead>Jumlah</TableHead>
                                    <TableHead>Pengangkutan</TableHead>
                                    <TableHead>Masa simpan</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="paginatedRecords.length === 0">
                                    <TableCell
                                        :colspan="10"
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
                                                        isCreateDialogOpen = true
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
                                    <TableCell>{{
                                        record.created_by?.name ||
                                        'Tidak tersedia'
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
                                            <span>{{
                                                record.source_name ||
                                                'Belum tercatat'
                                            }}</span>
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
                                        <div class="space-y-1">
                                            <div class="text-sm">
                                                {{
                                                    parseFloat(
                                                        String(
                                                            record.approved_hauled_quantity,
                                                        ),
                                                    ).toFixed(2)
                                                }}
                                                /
                                                {{
                                                    parseFloat(
                                                        String(record.quantity),
                                                    ).toFixed(2)
                                                }}
                                                {{ record.unit }}
                                            </div>
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                                    :class="
                                                        getOperationalStatusClass(
                                                            record.operational_status,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        record.operational_status_label
                                                    }}
                                                </span>
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Sisa
                                                    {{
                                                        parseFloat(
                                                            String(
                                                                record.remaining_quantity,
                                                            ),
                                                        ).toFixed(2)
                                                    }}
                                                    {{ record.unit }}
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <ExpiryBadge
                                            v-if="record.status === 'approved'"
                                            :reference-date="filters.as_of"
                                            :expiry-date="record.expiry_date"
                                            :remaining-quantity="
                                                record.remaining_quantity
                                            "
                                            :simulated="
                                                record.has_simulated_hauling
                                            "
                                            size="sm"
                                        />
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                            >Belum berlaku</span
                                        >
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
                                        <Button
                                            v-if="
                                                record.status === 'approved' &&
                                                record.remaining_quantity > 0
                                            "
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    wasteManagementRoutes.haulings.create.url(
                                                        {
                                                            query: {
                                                                waste_record:
                                                                    record.id,
                                                            },
                                                        },
                                                    )
                                                "
                                            >
                                                Ajukan angkut
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
                            :disabled="isLoading || currentPage === 1"
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
                            :disabled="isLoading || currentPage === totalPages"
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
                class="max-h-[90vh] max-w-3xl overflow-y-auto"
                :show-close-button="true"
            >
                <DialogHeader>
                    <DialogTitle>Tambah Catatan Limbah</DialogTitle>
                </DialogHeader>
                <CreateWasteRecordForm
                    v-if="props.wasteTypes"
                    :waste-types="props.wasteTypes"
                    :waste-sources="props.wasteSources"
                    @success="handleCreateSuccess"
                />
            </DialogContent>
        </Dialog>
    </WasteManagementLayout>
</template>
