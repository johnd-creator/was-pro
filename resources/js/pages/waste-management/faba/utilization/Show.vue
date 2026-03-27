<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaUtilizationMovement } from '@/types/faba';

const props = defineProps<{ entry: FabaUtilizationMovement }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pemanfaatan FABA',
        href: wasteManagementRoutes.faba.utilization.index.url(),
    },
    {
        title: props.entry.display_number,
        href: wasteManagementRoutes.faba.utilization.show(props.entry.id).url,
    },
];
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Detail Pemanfaatan FABA"
    >
        <Head title="Detail Pemanfaatan FABA" />
        <div class="space-y-6 p-6">
            <Heading
                :title="entry.display_number"
                description="Detail transaksi pemanfaatan FABA."
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
                    <div>
                        <strong>Tipe:</strong> {{ formatFabaMovementType(entry.movement_type) }}
                    </div>
                    <div>
                        <strong>Vendor:</strong> {{ entry.vendor?.name || '-' }}
                    </div>
                    <div>
                        <strong>Tujuan internal:</strong> {{ entry.internal_destination?.name || '-' }}
                    </div>
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
                    <div>
                        <strong>Dokumen:</strong>
                        {{ entry.document_number || '-' }}
                    </div>
                    <div>
                        <strong>Use-case:</strong>
                        {{ entry.purpose?.name || '-' }}
                    </div>
                    <div>
                        <strong>Tanggal dokumen:</strong>
                        {{ formatFabaDate(entry.document_date) }}
                    </div>
                    <div>
                        <strong>Lampiran:</strong>
                        {{ entry.attachment_path || '-' }}
                    </div>
                    <div><strong>Catatan:</strong> {{ entry.note || '-' }}</div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
