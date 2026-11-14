<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock3,
    Layers3,
    PackageSearch,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { index as plansIndex } from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingPlansController';
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
import CreateWasteHaulingForm from '@/components/waste-management/CreateWasteHaulingForm.vue';
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    compareWasteQuantities,
    summarizeWasteQuantities,
} from '@/lib/waste-quantity';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { HaulingPlan } from '@/types/hauling-plan';

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
    pending_actual_quantity?: number;
    plan_reserved_quantity?: number;
    available_quantity?: number;
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
    backlogContract?: number;
    backlogContracts?: number[];
    initialPage?: number;
    filters?: {
        search: string;
        urgency: 'all' | 'expired' | 'expiring_soon' | 'fresh';
        sort: 'priority' | 'latest' | 'remaining_high';
    };
    pagination?: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    metadataAvailable?: boolean;
    plansEnabled?: boolean;
    actualDateLimit?: string;
    actualDateTimezone?: string;
    approvedPlans?: HaulingPlan[];
    vendors?: { id: string; name: string }[];
    stats: {
        total_records: number;
        remaining_quantities: { unit: string; quantity: number }[];
        pending_requests: number;
        expired?: number;
        expiring_soon?: number;
        fresh?: number;
        attention_quantities?: { unit: string; quantity: number }[];
    };
}>();

const { scopedUrl } = useOrganizationContext();

const search = ref(props.filters?.search ?? '');
const urgencyFilter = ref<'all' | 'expired' | 'expiring_soon' | 'fresh'>(
    props.filters?.urgency ?? 'all',
);
const sortKey = ref<'priority' | 'latest' | 'remaining_high'>(
    props.filters?.sort ?? 'priority',
);
const clientPage = ref(props.initialPage ?? 1);
const serverPaginated = computed(
    () => props.backlogContract === 2 && !!props.pagination,
);
const supportsPagination = computed(
    () => props.backlogContracts?.includes(2) ?? false,
);
const currentPage = computed(() =>
    serverPaginated.value ? props.pagination!.current_page : clientPage.value,
);
const perPage = 10;
const loading = ref(false);
let filterTimer: ReturnType<typeof setTimeout> | undefined;
let cancelVisit: (() => void) | undefined;
let visitSequence = 0;
let ownVisit = false;

function cancelPending(): void {
    clearTimeout(filterTimer);
    filterTimer = undefined;
    visitSequence++;
    const cancel = cancelVisit;
    cancelVisit = undefined;
    cancel?.();
    loading.value = false;
}

const removeBeforeListener = router.on('before', () => {
    if (!ownVisit) cancelPending();
});
const removeNavigateListener = router.on('navigate', () => {
    cancelVisit = undefined;
    cancelPending();
});

watch(
    () => props.filters,
    (filters) => {
        if (!filters) return;
        search.value = filters.search;
        urgencyFilter.value = filters.urgency;
        sortKey.value = filters.sort;
    },
);
watch([search, urgencyFilter, sortKey], () => {
    cancelPending();
    clientPage.value = 1;
    if (
        !supportsPagination.value ||
        (search.value === props.filters?.search &&
            urgencyFilter.value === props.filters?.urgency &&
            sortKey.value === props.filters?.sort)
    )
        return;
    filterTimer = setTimeout(() => visitPage(1, true), 300);
});
onMounted(() => {
    window.addEventListener('popstate', cancelPending);
    if (supportsPagination.value && !serverPaginated.value)
        visitPage(props.initialPage ?? 1, true);
});
onBeforeUnmount(() => {
    cancelPending();
    removeBeforeListener();
    removeNavigateListener();
    window.removeEventListener('popstate', cancelPending);
});

function visitPage(page: number, replace = false): void {
    clearTimeout(filterTimer);
    clientPage.value = page;
    const sequence = ++visitSequence;
    ownVisit = true;
    try {
        router.get(
            scopedUrl(
                wasteManagementRoutes.haulings.index.url({
                    mergeQuery: {
                        search: search.value || null,
                        urgency: urgencyFilter.value,
                        sort: sortKey.value,
                        page,
                    },
                }),
            ),
            {},
            {
                headers: { 'X-Waspro-Backlog-Contract': '2' },
                preserveState: true,
                preserveScroll: true,
                replace,
                onCancelToken: (token) => {
                    cancelVisit = () => token.cancel();
                },
                onStart: () => {
                    loading.value = true;
                },
                onFinish: () => {
                    if (sequence === visitSequence) {
                        loading.value = false;
                        cancelVisit = undefined;
                    }
                },
            },
        );
    } finally {
        ownVisit = false;
    }
}

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
    router.reload({
        headers: supportsPagination.value
            ? { 'X-Waspro-Backlog-Contract': '2' }
            : {},
    });
}

const filteredRecords = computed(() => {
    if (serverPaginated.value) return props.records;
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
                return compareWasteQuantities(left, right);
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

const expiredCount = computed(() =>
    serverPaginated.value
        ? (props.stats.expired ?? 0)
        : expiredRecords.value.length,
);
const expiringSoonCount = computed(() =>
    serverPaginated.value
        ? (props.stats.expiring_soon ?? 0)
        : expiringSoonRecords.value.length,
);
const freshCount = computed(() =>
    serverPaginated.value
        ? (props.stats.fresh ?? 0)
        : freshRecords.value.length,
);
const attentionQuantityLabel = computed(() => {
    const quantities = serverPaginated.value
        ? (props.stats.attention_quantities ?? [])
        : summarizeWasteQuantities([
              ...expiredRecords.value,
              ...expiringSoonRecords.value,
          ]);
    return (
        quantities
            .map(({ unit, quantity }) => `${formatNumber(quantity)} ${unit}`)
            .join(', ') || 'Tidak ada sisa material'
    );
});
const hasAnyRecords = computed(() => props.stats.total_records > 0);
const hasActiveFilters = computed(
    () => search.value.trim().length > 0 || urgencyFilter.value !== 'all',
);
const totalPages = computed(() =>
    serverPaginated.value
        ? props.pagination!.last_page
        : Math.max(1, Math.ceil(filteredRecords.value.length / perPage)),
);
const paginatedRecords = computed(() => {
    if (serverPaginated.value) return props.records;
    const start = (currentPage.value - 1) * perPage;
    return filteredRecords.value.slice(start, start + perPage);
});
const pageRangeLabel = computed(() => {
    const total = serverPaginated.value
        ? props.pagination!.total
        : filteredRecords.value.length;
    if (total === 0) return '0 dari 0 limbah belum terkelola';
    const start = serverPaginated.value
        ? props.pagination!.from
        : (currentPage.value - 1) * perPage + 1;
    const end = serverPaginated.value
        ? props.pagination!.to
        : Math.min(start! + perPage - 1, total);
    return `${start}-${end} dari ${total} limbah belum terkelola`;
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
    clientPage.value = 1;
}

function resetFilters(): void {
    search.value = '';
    urgencyFilter.value = 'all';
    sortKey.value = 'priority';
    resetPage();
}

function changePage(page: number): void {
    const target = Math.min(Math.max(page, 1), totalPages.value);
    if (supportsPagination.value) visitPage(target);
    else clientPage.value = target;
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Pengangkutan Limbah"
    >
        <Head title="Pengangkutan Limbah" />

        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="space-y-4">
                <section class="space-y-4">
                    <div class="min-w-0">
                        <div class="space-y-4">
                            <div class="space-y-3">
                                <Heading title="Pengangkutan Limbah" />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Limbah Belum Terkelola:
                                    {{ stats.total_records }}
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
                                    {{ expiredCount + expiringSoonCount }}
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
                                        Limbah Belum Terkelola siap angkut
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ stats.total_records }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ freshCount }} limbah belum terkelola
                                        masih aman, sisanya butuh perhatian
                                        lebih dekat.
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
                                        {{ expiredCount + expiringSoonCount }}
                                    </p>
                                    <p
                                        class="mt-2 text-[12px] leading-5 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ attentionQuantityLabel }}
                                        berada di zona expired atau mendekati
                                        expired.
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
                                Cari dan saring limbah belum terkelola
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
                            />
                        </div>

                        <Select v-model="urgencyFilter">
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

                        <Select v-model="sortKey">
                            <SelectTrigger>
                                <SelectValue
                                    placeholder="Urutkan limbah belum terkelola"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="priority"
                                    >Prioritas tertinggi lebih dulu</SelectItem
                                >
                                <SelectItem value="latest"
                                    >Tanggal catatan terbaru</SelectItem
                                >
                                <SelectItem value="remaining_high"
                                    >Sisa terbesar per satuan</SelectItem
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
                                    <TableHead>Realisasi disetujui</TableHead>
                                    <TableHead>Saldo tercatat</TableHead>
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
                                                        ? 'Tidak ada limbah belum terkelola yang cocok'
                                                        : 'Belum ada limbah belum terkelola'
                                                }}
                                            </p>
                                            <p class="max-w-md text-xs">
                                                {{
                                                    hasAnyRecords
                                                        ? 'Ubah pencarian atau filter urgensi untuk melihat limbah lain.'
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
                                            :remaining-quantity="
                                                record.remaining_quantity
                                            "
                                            :simulated="
                                                record.hauling_history.some(
                                                    (entry) =>
                                                        entry.status ===
                                                            'approved' &&
                                                        entry.hauling_number.startsWith(
                                                            'SIMULASI-',
                                                        ),
                                                )
                                            "
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
                                                    record.pending_actual_quantity !==
                                                    undefined
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                Realisasi menunggu:
                                                {{
                                                    formatNumber(
                                                        record.pending_actual_quantity,
                                                    )
                                                }}
                                                {{ record.unit }}
                                            </div>
                                            <div
                                                v-if="
                                                    plansEnabled &&
                                                    record.plan_reserved_quantity !==
                                                        undefined
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                Alokasi rencana:
                                                {{
                                                    formatNumber(
                                                        record.plan_reserved_quantity,
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
                                                {{
                                                    plansEnabled
                                                        ? 'Rencana / Realisasi'
                                                        : 'Catat realisasi'
                                                }}
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
                            :disabled="loading || currentPage === 1"
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
                            :disabled="loading || currentPage === totalPages"
                            @click="changePage(currentPage + 1)"
                        >
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="plansEnabled" class="px-6 pb-6">
            <Button as-child variant="outline"
                ><Link :href="plansIndex.url()"
                    >Lihat semua rencana angkut</Link
                ></Button
            >
        </div>
        <!-- Create Dialog -->
        <Dialog v-model:open="isCreateDialogOpen">
            <DialogContent
                class="max-h-[92vh] w-[min(96vw,1080px)] overflow-y-auto p-0 sm:max-w-none"
                :show-close-button="true"
            >
                <DialogHeader class="border-b px-6 pt-6 pb-4 sm:px-8">
                    <DialogTitle>{{
                        plansEnabled
                            ? 'Rencana dan realisasi pengangkutan'
                            : 'Catat realisasi pengangkutan'
                    }}</DialogTitle>
                </DialogHeader>
                <div class="px-6 py-5 sm:px-8 sm:py-6">
                    <CreateWasteHaulingForm
                        v-if="selectedRecord"
                        :key="selectedRecord.id"
                        :record="selectedRecord"
                        :plans-enabled="plansEnabled"
                        :actual-date-limit="actualDateLimit"
                        :actual-date-timezone="actualDateTimezone"
                        :approved-plans="approvedPlans"
                        :metadata-available="metadataAvailable"
                        :vendors="vendors"
                        @success="handleCreateSuccess"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </WasteManagementLayout>
</template>
