<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Link from '@/components/OrganizationLink.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Props {
    records: Array<{
        id: string;
        record_number: string;
        waste_type: string;
        expiry_date: string;
        remaining_quantity: number;
        unit: string;
    }>;
    snapshot: {
        date: string;
        label: string;
        organization_id: string;
        organization_name: string;
        unit: string | null;
    };
}
const props = defineProps<Props>();
const back = dashboard.url({
    query: {
        month: props.snapshot.date.slice(0, 7),
        organization_id: props.snapshot.organization_id,
    },
});
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: back },
    { title: 'Limbah Expired', href: '' },
];
</script>

<template>
    <Head title="Snapshot Limbah Expired" />
    <WasteManagementLayout :breadcrumbs="breadcrumbs" title="Limbah Expired">
        <div class="space-y-5 p-4 sm:p-6">
            <Heading
                title="Limbah Expired"
                :description="`${snapshot.organization_name} · Snapshot ${snapshot.label}${snapshot.unit ? ' · Satuan ' + snapshot.unit : ''}`"
            />
            <p
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                {{ records.length }} catatan approved dengan sisa belum
                terangkut dan masa simpan terlewati pada tanggal snapshot. Hanya
                catatan sesuai hak akses Anda ditampilkan. Tampilan ini hanya
                untuk pembacaan historis.
            </p>
            <div class="wm-panel overflow-x-auto rounded-xl border">
                <Table>
                    <TableHeader
                        ><TableRow
                            ><TableHead>Nomor catatan</TableHead
                            ><TableHead>Jenis limbah</TableHead
                            ><TableHead>Batas simpan</TableHead
                            ><TableHead>Sisa pada snapshot</TableHead></TableRow
                        ></TableHeader
                    >
                    <TableBody>
                        <TableRow v-for="record in records" :key="record.id"
                            ><TableCell class="font-mono text-xs">{{
                                record.record_number
                            }}</TableCell
                            ><TableCell>{{ record.waste_type }}</TableCell
                            ><TableCell>{{ record.expiry_date }}</TableCell
                            ><TableCell
                                class="font-semibold text-red-700 dark:text-red-300"
                                >{{
                                    record.remaining_quantity.toLocaleString(
                                        'id-ID',
                                        { maximumFractionDigits: 2 },
                                    )
                                }}
                                {{ record.unit }}</TableCell
                            ></TableRow
                        >
                        <TableRow v-if="!records.length"
                            ><TableCell :colspan="4" class="py-8 text-center"
                                >Tidak ada sisa limbah expired yang dapat Anda
                                akses pada snapshot ini.</TableCell
                            ></TableRow
                        >
                    </TableBody>
                </Table>
            </div>
            <Link
                :href="back"
                class="text-sm font-semibold text-blue-700 dark:text-blue-300"
                >Kembali ke dashboard</Link
            >
        </div>
    </WasteManagementLayout>
</template>
