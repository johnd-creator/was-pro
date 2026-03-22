<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaEntryType,
    formatFabaMaterial,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaProductionEntry } from '@/types/faba';

const props = defineProps<{ entry: FabaProductionEntry }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Produksi FABA',
        href: wasteManagementRoutes.faba.production.index.url(),
    },
    {
        title: props.entry.entry_number,
        href: wasteManagementRoutes.faba.production.show(props.entry.id).url,
    },
];
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Detail Produksi FABA"
    >
        <Head title="Detail Produksi FABA" />
        <div class="space-y-6 p-6">
            <Heading
                :title="entry.entry_number"
                description="Detail transaksi produksi FABA."
            />
            <Card>
                <CardHeader><CardTitle>Informasi utama</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div>
                        <strong>Tanggal:</strong> {{ formatFabaDate(entry.transaction_date) }}
                    </div>
                    <div>
                        <strong>Material:</strong> {{ formatFabaMaterial(entry.material_type) }}
                    </div>
                    <div><strong>Tipe:</strong> {{ formatFabaEntryType(entry.entry_type) }}</div>
                    <div>
                        <strong>Jumlah:</strong> {{ entry.quantity }}
                        {{ entry.unit }}
                    </div>
                    <div>
                        <strong>Status bulan:</strong>
                        <Badge variant="secondary">
                            {{ formatFabaStatus(entry.approval_status) }}
                        </Badge>
                    </div>
                    <div><strong>Catatan:</strong> {{ entry.note || '-' }}</div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
