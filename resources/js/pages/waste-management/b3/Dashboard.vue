<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    ClipboardList,
    PackageSearch,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Link from '@/components/OrganizationLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import balanceRoutes from '@/routes/waste-management/balance';
import { index as recordsIndex, show } from '@/routes/waste-management/records';

interface Amounts {
    opening: number;
    incoming: number;
    outgoing: number;
    closing: number;
}
interface BalanceRow extends Amounts {
    waste_type_id: string;
    waste_type: string;
    code: string;
    unit: string;
    anomaly_count: number;
}
interface Attention {
    id: string;
    record_number: string;
    waste_type: string;
    quantity: number;
    unit: string;
    expiry_date: string | null;
    days_remaining: number | null;
    urgency: string;
    input_by: string;
}
interface Recent {
    id: string;
    record_number: string;
    date: string;
    waste_type: string;
    quantity: number;
    unit: string;
    status: string;
    input_by: string;
}
interface Props {
    organizationName: string;
    report: {
        basis: string;
        start_date: string;
        end_date: string;
        rows: BalanceRow[];
        totals: Array<Amounts & { unit: string }>;
    };
    stats: {
        period_records: number;
        pending_records: number;
        remaining_records: number;
        expired_records: number;
        soon_records: number;
        unknown_expiry_records: number;
        attention_count: number;
        anomaly_count: number;
    };
    attention: Attention[];
    recentRecords: Recent[];
}
const props = defineProps<Props>();
const page = usePage();
const { scopedUrl } = useOrganizationContext();
const start = ref(props.report.start_date);
const end = ref(props.report.end_date);
const loading = ref(false);
const dashboardUrl = '/waste-management/b3/dashboard';
const breadcrumbs = [{ title: 'Dashboard Limbah B3', href: dashboardUrl }];
const balanceUrl = computed(() =>
    balanceRoutes.index.url({
        query: {
            start_date: props.report.start_date,
            end_date: props.report.end_date,
        },
    }),
);
const periodRecordsUrl = computed(() =>
    recordsIndex.url({ query: { month: props.report.end_date.slice(0, 7) } }),
);
const number = (value: number) =>
    value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
const statusLabels: Record<string, string> = {
    draft: 'Draf',
    pending_review: 'Menunggu ditinjau',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};
watch(
    () => props.report,
    (report) => {
        start.value = report.start_date;
        end.value = report.end_date;
    },
);
function apply() {
    router.get(
        scopedUrl(dashboardUrl),
        { start_date: start.value, end_date: end.value },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbs"
        title="Dashboard Limbah B3"
    >
        <Head title="Dashboard Limbah B3" />
        <div class="space-y-4 p-4 sm:p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Dashboard Limbah B3"
                    :description="organizationName"
                />
                <Link
                    :href="balanceUrl"
                    class="inline-flex min-h-11 items-center rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    >Buka Neraca Limbah B3</Link
                >
            </div>
            <form
                class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
                @submit.prevent="apply"
            >
                <div class="space-y-1.5">
                    <Label for="b3-start">Tanggal awal</Label>
                    <Input id="b3-start" v-model="start" type="date" required />
                    <p
                        v-if="page.props.errors.start_date"
                        class="text-sm text-destructive"
                    >
                        {{ page.props.errors.start_date }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="b3-end">Tanggal akhir</Label>
                    <Input id="b3-end" v-model="end" type="date" required />
                    <p
                        v-if="page.props.errors.end_date"
                        class="text-sm text-destructive"
                    >
                        {{ page.props.errors.end_date }}
                    </p>
                </div>
                <Button type="submit" class="min-h-11" :disabled="loading">{{
                    loading ? 'Memuat...' : 'Tampilkan'
                }}</Button>
                <p class="text-xs text-muted-foreground">
                    Tanggal transaksi termasuk kedua batas periode.
                </p>
            </form>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a
                    href="#b3-recent"
                    class="rounded-xl border bg-card p-4 transition-colors hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <ClipboardList class="mb-3 size-5 text-primary" />
                    <p class="text-xs font-medium text-muted-foreground">
                        Catatan dalam periode
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ number(stats.period_records) }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Termasuk draf dan pengajuan
                    </p>
                </a>
                <Link
                    :href="balanceUrl"
                    class="rounded-xl border bg-card p-4 transition-colors hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <PackageSearch class="mb-3 size-5 text-primary" />
                    <p class="text-xs font-medium text-muted-foreground">
                        Limbah Belum Terkelola
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ number(stats.remaining_records) }}
                        <span class="text-sm font-normal">catatan</span>
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Sisa disetujui per {{ report.end_date }}
                    </p>
                </Link>
                <a
                    href="#b3-attention"
                    class="rounded-xl border bg-card p-4 transition-colors hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <TriangleAlert class="mb-3 size-5 text-amber-600" />
                    <p class="text-xs font-medium text-muted-foreground">
                        Perlu tindak lanjut
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ number(stats.attention_count) }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ stats.expired_records }} lewat batas ·
                        {{ stats.soon_records }} ≤ 7 hari ·
                        {{ stats.unknown_expiry_records }} tanpa batas simpan
                    </p>
                </a>
                <Link
                    :href="balanceUrl"
                    class="rounded-xl border bg-card p-4 transition-colors hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <TriangleAlert class="mb-3 size-5 text-muted-foreground" />
                    <p class="text-xs font-medium text-muted-foreground">
                        Temuan neraca
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ number(stats.anomaly_count) }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Periksa satuan, tanggal, atau saldo
                    </p>
                </Link>
            </div>
            <section class="rounded-xl border bg-card">
                <div class="border-b p-4">
                    <h2 class="font-semibold">
                        Rekapitulasi Limbah dan Tindak Lanjut
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Saldo awal + masuk − keluar = saldo akhir. Tiap satuan
                        dihitung terpisah.
                    </p>
                </div>
                <div
                    v-if="!report.totals.length"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Belum ada transaksi yang disetujui sampai akhir periode.
                </div>
                <div v-else class="grid gap-3 p-4 lg:grid-cols-3">
                    <Link
                        v-for="total in report.totals"
                        :key="total.unit"
                        :href="balanceUrl"
                        class="rounded-lg border bg-muted/20 p-4 hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground"
                        >
                            SATUAN {{ total.unit }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums">
                            {{ number(total.closing) }}
                            <span class="text-sm font-normal">{{
                                total.unit
                            }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Saldo akhir {{ report.end_date }}
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span
                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                    ><ArrowDownToLine class="size-3.5" />
                                    Masuk</span
                                ><strong class="tabular-nums">{{
                                    number(total.incoming)
                                }}</strong>
                            </div>
                            <div>
                                <span
                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                    ><ArrowUpFromLine class="size-3.5" />
                                    Keluar</span
                                ><strong class="tabular-nums">{{
                                    number(total.outgoing)
                                }}</strong>
                            </div>
                        </div>
                        <p
                            class="mt-3 border-t pt-2 text-xs text-muted-foreground"
                        >
                            Saldo awal {{ number(total.opening) }}
                            {{ total.unit }}
                        </p>
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader
                            ><TableRow
                                ><TableHead>Jenis limbah</TableHead
                                ><TableHead>Satuan</TableHead
                                ><TableHead class="text-right"
                                    >Saldo awal</TableHead
                                ><TableHead class="text-right">Masuk</TableHead
                                ><TableHead class="text-right">Keluar</TableHead
                                ><TableHead class="text-right"
                                    >Saldo akhir</TableHead
                                ></TableRow
                            ></TableHeader
                        >
                        <TableBody>
                            <TableRow
                                v-for="row in report.rows"
                                :key="row.waste_type_id + row.unit"
                            >
                                <TableCell
                                    ><span class="font-medium">{{
                                        row.waste_type
                                    }}</span
                                    ><span
                                        class="block text-xs text-muted-foreground"
                                        >{{ row.code }}</span
                                    ></TableCell
                                ><TableCell>{{ row.unit }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{
                                    number(row.opening)
                                }}</TableCell
                                ><TableCell class="text-right tabular-nums">{{
                                    number(row.incoming)
                                }}</TableCell
                                ><TableCell class="text-right tabular-nums">{{
                                    number(row.outgoing)
                                }}</TableCell
                                ><TableCell
                                    class="text-right font-semibold tabular-nums"
                                    :class="{
                                        'text-destructive': row.closing < 0,
                                    }"
                                    >{{ number(row.closing) }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </section>
            <div class="grid gap-5 xl:grid-cols-2">
                <section
                    id="b3-attention"
                    class="scroll-mt-4 rounded-xl border bg-card"
                >
                    <div class="border-b p-4">
                        <h2 class="font-semibold">Prioritas pengelolaan</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Sisa limbah per {{ report.end_date }}. Menampilkan
                            {{ attention.length }} dari
                            {{ stats.attention_count }} catatan.
                        </p>
                    </div>
                    <p
                        v-if="!attention.length"
                        class="p-6 text-sm text-muted-foreground"
                    >
                        Tidak ada sisa limbah yang melewati batas, mendekati
                        batas 7 hari, atau belum memiliki batas simpan.
                    </p>
                    <div v-else class="divide-y">
                        <Link
                            v-for="item in attention"
                            :key="item.id"
                            :href="show.url(item.id)"
                            class="flex items-start justify-between gap-3 p-4 hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    {{ item.waste_type }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ item.record_number }} · Input:
                                    {{ item.input_by }}
                                </p>
                                <p
                                    class="mt-1 text-xs"
                                    :class="
                                        item.urgency === 'expired'
                                            ? 'text-destructive'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        item.expiry_date
                                            ? 'Batas simpan: ' +
                                              item.expiry_date
                                            : 'Batas simpan belum tersedia'
                                    }}
                                </p>
                            </div>
                            <strong class="shrink-0 text-sm tabular-nums"
                                >{{ number(item.quantity) }}
                                {{ item.unit }}</strong
                            >
                        </Link>
                    </div>
                </section>
                <section
                    id="b3-recent"
                    class="scroll-mt-4 rounded-xl border bg-card"
                >
                    <div
                        class="flex items-start justify-between gap-3 border-b p-4"
                    >
                        <div>
                            <h2 class="font-semibold">Catatan terbaru</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ stats.pending_records }} pengajuan dalam
                                periode menunggu ditinjau.
                            </p>
                        </div>
                        <Link
                            :href="periodRecordsUrl"
                            class="text-xs font-medium text-primary hover:underline"
                            >Buka catatan limbah</Link
                        >
                    </div>
                    <p
                        v-if="!recentRecords.length"
                        class="p-6 text-sm text-muted-foreground"
                    >
                        Belum ada catatan dalam periode ini.
                    </p>
                    <div v-else class="divide-y">
                        <Link
                            v-for="item in recentRecords"
                            :key="item.id"
                            :href="show.url(item.id)"
                            class="flex items-start justify-between gap-3 p-4 hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    {{ item.waste_type }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ item.date }} · {{ item.record_number }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Input: {{ item.input_by }} ·
                                    {{
                                        statusLabels[item.status] ?? item.status
                                    }}
                                </p>
                            </div>
                            <span class="shrink-0 text-sm tabular-nums"
                                >{{ number(item.quantity) }}
                                {{ item.unit }}</span
                            >
                        </Link>
                    </div>
                </section>
            </div>
            <p
                class="rounded-lg bg-muted/50 p-3 text-xs leading-relaxed text-muted-foreground"
            >
                {{ report.basis }} Urgensi dihitung terhadap tanggal akhir yang
                dipilih, bukan status persetujuan pada masa lampau.
            </p>
        </div>
    </WasteManagementLayout>
</template>
