<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
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
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import balanceRoutes from '@/routes/waste-management/balance';
import { show } from '@/routes/waste-management/records';
import type { BreadcrumbItem } from '@/types';

interface Amounts {
    opening: number;
    incoming: number;
    outgoing: number;
    closing: number;
}
interface Reference extends Amounts {
    id: string;
    record_number: string;
    date: string;
    source: string | null;
    anomalies: string[];
}
interface Row extends Amounts {
    waste_type_id: string;
    waste_type: string;
    code: string;
    unit: string;
    references: Reference[];
    anomaly_count: number;
}
interface Report {
    basis: string;
    start_date: string;
    end_date: string;
    rows: Row[];
    totals: Array<Amounts & { unit: string }>;
}
const props = defineProps<{ report: Report }>();
const page = usePage();
const { scopedUrl, download } = useOrganizationContext();
const start = ref(props.report.start_date);
const end = ref(props.report.end_date);
const loading = ref(false);
watch(
    () => props.report,
    (report) => {
        start.value = report.start_date;
        end.value = report.end_date;
    },
);
const anomalies = computed(() =>
    props.report.rows.reduce((sum, row) => sum + row.anomaly_count, 0),
);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Neraca Operasional Limbah', href: balanceRoutes.index.url() },
];
const format = (value: number) =>
    value.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
function apply() {
    router.get(
        scopedUrl(balanceRoutes.index.url()),
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
function exportCsv() {
    download(
        balanceRoutes.export.csv.url({
            query: {
                start_date: props.report.start_date,
                end_date: props.report.end_date,
            },
        }),
    );
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbs"
        title="Neraca Operasional Limbah"
    >
        <Head title="Neraca Operasional Limbah" />
        <div class="space-y-6 p-4 sm:p-6">
            <Heading
                title="Neraca Operasional Limbah"
                description="Saldo awal + masuk - keluar = saldo akhir, per jenis limbah dan satuan."
            />
            <p
                class="rounded-xl border bg-muted/50 p-4 text-sm leading-relaxed"
            >
                {{ report.basis }}
            </p>
            <form
                class="flex flex-wrap items-start gap-4"
                @submit.prevent="apply"
            >
                <div class="space-y-2">
                    <Label for="balance-start">Tanggal awal (termasuk)</Label>
                    <Input
                        id="balance-start"
                        v-model="start"
                        type="date"
                        required
                        aria-describedby="balance-start-error"
                    />
                    <p
                        id="balance-start-error"
                        class="text-sm text-destructive"
                    >
                        {{ page.props.errors.start_date }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="balance-end">Tanggal akhir (termasuk)</Label>
                    <Input
                        id="balance-end"
                        v-model="end"
                        type="date"
                        required
                        aria-describedby="balance-end-error"
                    />
                    <p id="balance-end-error" class="text-sm text-destructive">
                        {{ page.props.errors.end_date }}
                    </p>
                </div>
                <Button
                    type="submit"
                    class="mt-6 min-h-11"
                    :disabled="loading"
                    >{{ loading ? 'Memuat...' : 'Tampilkan' }}</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="mt-6 min-h-11"
                    :disabled="loading"
                    @click="exportCsv"
                    >Ekspor CSV hasil tampil</Button
                >
            </form>
            <div class="flex flex-wrap justify-between gap-2 text-sm">
                <p>
                    Periode tampil:
                    <strong
                        >{{ report.start_date }} s.d.
                        {{ report.end_date }}</strong
                    >
                </p>
                <p
                    v-if="anomalies"
                    role="status"
                    class="font-semibold text-destructive"
                >
                    {{ anomalies }} temuan perlu diperiksa. Lihat referensi
                    catatan di bawah.
                </p>
            </div>
            <div class="wm-panel overflow-x-auto rounded-xl border">
                <Table>
                    <TableHeader
                        ><TableRow
                            ><TableHead>Jenis limbah</TableHead
                            ><TableHead>Satuan</TableHead
                            ><TableHead class="text-right">Saldo awal</TableHead
                            ><TableHead class="text-right">Masuk</TableHead
                            ><TableHead class="text-right">Keluar</TableHead
                            ><TableHead class="text-right"
                                >Saldo akhir</TableHead
                            ><TableHead>Perlu diperiksa</TableHead></TableRow
                        ></TableHeader
                    >
                    <TableBody>
                        <TableRow
                            v-for="row in report.rows"
                            :key="`${row.waste_type_id}-${row.unit}`"
                        >
                            <TableCell
                                ><span class="font-medium">{{
                                    row.waste_type
                                }}</span>
                                <p class="text-xs text-muted-foreground">
                                    {{ row.code }}
                                </p></TableCell
                            >
                            <TableCell>{{ row.unit }}</TableCell
                            ><TableCell class="text-right tabular-nums">{{
                                format(row.opening)
                            }}</TableCell
                            ><TableCell class="text-right tabular-nums">{{
                                format(row.incoming)
                            }}</TableCell
                            ><TableCell class="text-right tabular-nums">{{
                                format(row.outgoing)
                            }}</TableCell
                            ><TableCell
                                class="text-right font-semibold tabular-nums"
                                :class="{ 'text-destructive': row.closing < 0 }"
                                >{{ format(row.closing) }}</TableCell
                            ><TableCell>{{
                                row.anomaly_count || 'Tidak ada'
                            }}</TableCell>
                        </TableRow>
                        <TableRow v-if="!report.rows.length"
                            ><TableCell
                                :colspan="7"
                                class="py-8 text-center text-muted-foreground"
                                >Tidak ada transaksi disetujui yang dapat Anda
                                akses sampai tanggal akhir.</TableCell
                            ></TableRow
                        >
                        <TableRow
                            v-for="total in report.totals"
                            :key="total.unit"
                            class="bg-muted/50 font-semibold"
                            ><TableCell>Total per satuan</TableCell
                            ><TableCell>{{ total.unit }}</TableCell
                            ><TableCell class="text-right tabular-nums">{{
                                format(total.opening)
                            }}</TableCell
                            ><TableCell class="text-right tabular-nums">{{
                                format(total.incoming)
                            }}</TableCell
                            ><TableCell class="text-right tabular-nums">{{
                                format(total.outgoing)
                            }}</TableCell
                            ><TableCell class="text-right tabular-nums">{{
                                format(total.closing)
                            }}</TableCell
                            ><TableCell
                        /></TableRow>
                    </TableBody>
                </Table>
            </div>
            <section class="space-y-3" aria-labelledby="balance-references">
                <h2 id="balance-references" class="text-lg font-semibold">
                    Referensi dan pemeriksaan data
                </h2>
                <p class="text-sm text-muted-foreground">
                    Rincian kontribusi setiap catatan, termasuk saldo bawaan
                    sebelum periode. Asal limbah adalah teks asal pada catatan,
                    bukan lokasi TPS terstruktur. Anomali adalah penanda data
                    yang perlu diperiksa, bukan kategori limbah: saldo awal atau
                    akhir negatif, tanggal pengangkutan sebelum tanggal catatan,
                    atau satuan pengangkutan berbeda dari catatan. Satu catatan
                    dapat memiliki lebih dari satu temuan.
                </p>
                <details
                    v-for="row in report.rows"
                    :key="`${row.waste_type_id}-${row.unit}`"
                    class="rounded-xl border p-4"
                >
                    <summary
                        class="cursor-pointer rounded font-medium focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        {{ row.waste_type }} · {{ row.unit }} ·
                        {{ row.references.length }} catatan<span
                            v-if="row.anomaly_count"
                            class="ml-2 text-destructive"
                            >({{ row.anomaly_count }} temuan)</span
                        >
                    </summary>
                    <ul class="mt-4 divide-y">
                        <li
                            v-for="reference in row.references"
                            :key="reference.id"
                            class="space-y-2 py-3 text-sm"
                        >
                            <div class="flex flex-wrap justify-between gap-2">
                                <Link
                                    :href="show.url(reference.id)"
                                    class="font-medium text-primary underline underline-offset-4"
                                    >{{ reference.record_number }}</Link
                                ><span
                                    >{{ reference.date }} · Asal limbah:
                                    {{ reference.source || '-' }}</span
                                >
                            </div>
                            <p class="tabular-nums">
                                {{ format(reference.opening) }} +
                                {{ format(reference.incoming) }} -
                                {{ format(reference.outgoing) }} =
                                <strong
                                    >{{ format(reference.closing) }}
                                    {{ row.unit }}</strong
                                >
                            </p>
                            <ul
                                v-if="reference.anomalies.length"
                                class="list-inside list-disc text-destructive"
                            >
                                <li
                                    v-for="anomaly in reference.anomalies"
                                    :key="anomaly"
                                >
                                    {{ anomaly }}
                                </li>
                            </ul>
                        </li>
                    </ul>
                </details>
            </section>
        </div>
    </WasteManagementLayout>
</template>
