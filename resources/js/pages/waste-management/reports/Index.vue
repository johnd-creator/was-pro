<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText, Scale, Warehouse } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import * as ReportController from '@/actions/App/Http/Controllers/WasteManagement/WasteReportsController';
import Link from '@/components/OrganizationLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import balanceRoutes from '@/routes/waste-management/balance';
import type { BreadcrumbItem } from '@/types';

interface Filters {
    start_date: string;
    end_date: string;
    year: number;
    document_number?: string | null;
    document_date?: string | null;
    revision?: string | null;
    location?: string | null;
    manager_name?: string | null;
    environment_manager_name?: string | null;
}
interface Report {
    organization: string;
    scope_label: string;
    start_date: string;
    end_date: string;
    rows: Array<{ anomaly_count: number }>;
    totals: Array<{
        unit: string;
        opening: number;
        incoming: number;
        outgoing: number;
        closing: number;
    }>;
}
const props = defineProps<{ report: Report; filters: Filters }>();
const page = usePage();
const { scopedUrl, download } = useOrganizationContext();
function normalized(filters: Filters) {
    return {
        ...filters,
        document_number: filters.document_number ?? '',
        document_date: filters.document_date ?? '',
        revision: filters.revision ?? '',
        location: filters.location ?? '',
        manager_name: filters.manager_name ?? '',
        environment_manager_name: filters.environment_manager_name ?? '',
    };
}
const form = ref(normalized(props.filters));
const loading = ref(false);
watch(
    () => props.filters,
    (filters) => {
        form.value = normalized(filters);
    },
);
const dirty = computed(
    () =>
        JSON.stringify(form.value) !==
        JSON.stringify(normalized(props.filters)),
);
const anomalies = computed(() =>
    props.report.rows.reduce((sum, row) => sum + row.anomaly_count, 0),
);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laporan Limbah B3', href: ReportController.index.url() },
];
const metadataFields = [
    { key: 'document_number', label: 'Nomor dokumen', type: 'text' },
    { key: 'document_date', label: 'Tanggal terbit dokumen', type: 'date' },
    { key: 'revision', label: 'Revisi', type: 'text' },
    { key: 'location', label: 'Tempat penandatanganan', type: 'text' },
    { key: 'manager_name', label: 'Nama Manager Operasi', type: 'text' },
    {
        key: 'environment_manager_name',
        label: 'Nama Asman K3 dan Lingkungan',
        type: 'text',
    },
] as const;
function apply() {
    router.get(
        scopedUrl(ReportController.index.url()),
        { ...form.value },
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
function exportReport(kind: 'storage' | 'annual', format: 'xlsx' | 'pdf') {
    const action =
        kind === 'storage'
            ? format === 'xlsx'
                ? ReportController.storageXlsx
                : ReportController.storagePdf
            : format === 'xlsx'
              ? ReportController.balanceXlsx
              : ReportController.balancePdf;
    download(action.url({ query: { ...props.filters } }));
}
function quarter(value: number) {
    const year = Number(form.value.year);
    const month = (value - 1) * 3 + 1;
    form.value.start_date = `${year}-${String(month).padStart(2, '0')}-01`;
    const endDay = new Date(year, month + 2, 0).getDate();
    form.value.end_date = `${year}-${String(month + 2).padStart(2, '0')}-${endDay}`;
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbs" title="Laporan Limbah B3">
        <Head title="Laporan Limbah B3" />
        <div class="space-y-4 p-4 sm:p-4">
            <Heading title="Laporan Limbah B3" />
            <div class="rounded-xl border bg-muted/40 px-4 py-3 text-sm">
                <strong>{{ report.organization }}</strong>
                <p class="mt-1 text-muted-foreground">
                    {{ report.scope_label }}. Hanya transaksi disetujui; rencana
                    angkut tidak mengurangi saldo.
                </p>
            </div>
            <form
                class="space-y-4 rounded-xl border p-4"
                @submit.prevent="apply"
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="report-year">Tahun neraca</Label>
                        <Input
                            id="report-year"
                            v-model.number="form.year"
                            type="number"
                            min="2000"
                            max="2100"
                            required
                        />
                        <p class="text-sm text-destructive">
                            {{ page.props.errors.year }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="report-start"
                            >Penyimpanan: tanggal awal</Label
                        >
                        <Input
                            id="report-start"
                            v-model="form.start_date"
                            type="date"
                            required
                        />
                        <p class="text-sm text-destructive">
                            {{ page.props.errors.start_date }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="report-end"
                            >Penyimpanan: tanggal akhir</Label
                        >
                        <Input
                            id="report-end"
                            v-model="form.end_date"
                            type="date"
                            required
                        />
                        <p class="text-sm text-destructive">
                            {{ page.props.errors.end_date }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-muted-foreground"
                        >Pilih periode:</span
                    >
                    <Button
                        v-for="value in 4"
                        :key="value"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="quarter(value)"
                        >Triwulan {{ value }}</Button
                    >
                </div>
                <details class="rounded-lg border p-3">
                    <summary class="cursor-pointer text-sm font-medium">
                        Metadata dokumen dan penandatangan (opsional)
                    </summary>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Tidak menyalin nomor, tanggal, atau tanda tangan dari
                        contoh. Kolom kosong akan ditandai belum diisi. Nama
                        yang diisi bukan persetujuan digital.
                    </p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="field in metadataFields"
                            :key="field.key"
                            class="space-y-2"
                        >
                            <Label :for="'report-' + field.key">{{
                                field.label
                            }}</Label>
                            <Input
                                :id="'report-' + field.key"
                                v-model="form[field.key]"
                                :type="field.type"
                                maxlength="150"
                            />
                            <p class="text-sm text-destructive">
                                {{ page.props.errors[field.key] }}
                            </p>
                        </div>
                    </div>
                </details>
                <div class="flex flex-wrap items-center gap-3">
                    <Button :disabled="loading" type="submit">{{
                        loading ? 'Memuat...' : 'Terapkan periode dan metadata'
                    }}</Button>
                    <p
                        v-if="dirty"
                        role="status"
                        class="text-sm text-amber-700 dark:text-amber-300"
                    >
                        Terapkan perubahan sebelum mengunduh.
                    </p>
                </div>
            </form>
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border bg-card p-5">
                    <div class="flex items-start gap-3">
                        <Warehouse class="mt-1 h-6 w-6 text-emerald-600" />
                        <div>
                            <h2 class="text-lg font-semibold">
                                Lembar Kegiatan Penyimpanan
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Masuk, keluar, tujuan penyerahan, nomor dokumen,
                                dan sisa per catatan.
                            </p>
                        </div>
                    </div>
                    <p class="my-4 text-sm">
                        {{ report.start_date }} s.d. {{ report.end_date }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            :disabled="loading || dirty"
                            @click="exportReport('storage', 'xlsx')"
                            ><FileSpreadsheet
                                class="mr-2 h-4 w-4"
                            />Excel</Button
                        ><Button
                            variant="outline"
                            :disabled="loading || dirty"
                            @click="exportReport('storage', 'pdf')"
                            ><FileText class="mr-2 h-4 w-4" />PDF</Button
                        >
                    </div>
                </section>
                <section class="rounded-xl border bg-card p-5">
                    <div class="flex items-start gap-3">
                        <Scale class="mt-1 h-6 w-6 text-blue-600" />
                        <div>
                            <h2 class="text-lg font-semibold">
                                Neraca Limbah B3 Tahunan
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Saldo awal, rincian Januari–Desember, total
                                masuk, keluar, simpan, dan balancing.
                            </p>
                        </div>
                    </div>
                    <p class="my-4 text-sm">
                        Tahun {{ filters.year }} · PDF A3 landscape
                    </p>
                    <div class="flex gap-2">
                        <Button
                            :disabled="loading || dirty"
                            @click="exportReport('annual', 'xlsx')"
                            ><FileSpreadsheet
                                class="mr-2 h-4 w-4"
                            />Excel</Button
                        ><Button
                            variant="outline"
                            :disabled="loading || dirty"
                            @click="exportReport('annual', 'pdf')"
                            ><FileText class="mr-2 h-4 w-4" />PDF</Button
                        >
                    </div>
                </section>
            </div>
            <details
                class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <summary class="cursor-pointer font-medium">
                    Tentang laporan
                </summary>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>
                        Kg dikonversi ke ton; liter dan satuan lainnya tetap
                        terpisah.
                    </li>
                    <li>
                        Pemanfaatan B3 belum dipilah dari pengangkutan. Kolom
                        Manfaatkan ditandai -, artinya belum dipilah, bukan
                        diasumsikan nol.
                    </li>
                    <li>
                        Sumber limbah lama tetap ditampilkan jika tercatat. Data
                        yang tidak tersedia tidak diisi dengan perkiraan.
                    </li>
                    <li>
                        Dokumen belum ditandatangani dan mengikuti status
                        persetujuan saat diunduh.
                    </li>
                </ul>
                <p v-if="anomalies" class="mt-2 font-medium">
                    {{ anomalies }} anomali ditemukan pada periode penyimpanan.
                    Periksa neraca sebelum menggunakan laporan.
                </p>
            </details>
            <p
                v-if="page.props.errors.export"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ page.props.errors.export }}
            </p>
            <Button variant="outline" as-child
                ><Link
                    :href="
                        balanceRoutes.index.url({
                            query: {
                                start_date: report.start_date,
                                end_date: report.end_date,
                            },
                        })
                    "
                    >Periksa rincian Neraca Limbah B3</Link
                ></Button
            >
        </div>
    </WasteManagementLayout>
</template>
