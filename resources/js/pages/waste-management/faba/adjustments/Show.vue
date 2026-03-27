<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaDate, formatFabaMaterial, formatFabaMovementType, formatFabaStatus } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    entry: {
        id: string;
        display_number: string;
        transaction_date: string;
        material_type: string;
        movement_type: string;
        quantity: number;
        unit: string;
        note: string | null;
        period_label: string;
        approval_status: string;
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Adjustment FABA', href: wasteManagementRoutes.faba.adjustments.index.url() },
    { title: props.entry.display_number, href: wasteManagementRoutes.faba.adjustments.show(props.entry.id).url },
];
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Detail Adjustment FABA">
        <Head title="Detail Adjustment FABA" />
        <div class="space-y-6 p-6">
            <Heading :title="entry.display_number" description="Detail movement adjustment FABA." />
            <Card>
                <CardHeader><CardTitle>Informasi utama</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div><strong>Tanggal:</strong> {{ formatFabaDate(entry.transaction_date) }}</div>
                    <div><strong>Material:</strong> {{ formatFabaMaterial(entry.material_type) }}</div>
                    <div><strong>Tipe:</strong> {{ formatFabaMovementType(entry.movement_type) }}</div>
                    <div><strong>Jumlah:</strong> {{ entry.quantity }} {{ entry.unit }}</div>
                    <div><strong>Periode:</strong> {{ entry.period_label }}</div>
                    <div><strong>Status:</strong> <Badge variant="secondary">{{ formatFabaStatus(entry.approval_status) }}</Badge></div>
                    <div class="md:col-span-2"><strong>Alasan koreksi:</strong> {{ entry.note || '-' }}</div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
