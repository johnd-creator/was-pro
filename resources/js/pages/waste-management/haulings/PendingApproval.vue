<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Link from '@/components/OrganizationLink.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface HaulingItem {
    id: string;
    hauling_number: string;
    hauling_date: string;
    quantity: number;
    unit: string;
    submitted_at: string | null;
    waste_record: {
        record_number: string | null;
        waste_type: { name: string | null } | null;
    };
    created_by: { name: string } | null;
}

defineProps<{
    haulings: HaulingItem[];
    plansAvailable: boolean;
    pendingPlans: Array<{
        id: string;
        title: string;
        description: string;
        source_url: string;
    }>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pengangkutan Limbah',
        href: wasteManagementRoutes.haulings.index.url(),
    },
    {
        title: 'Pending Approval',
        href: wasteManagementRoutes.haulings.pendingApproval.url(),
    },
];

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Approval Pengangkutan"
    >
        <Head title="Approval Pengangkutan Limbah" />

        <div class="min-w-0 space-y-4 p-4">
            <Heading title="Approval Pengangkutan" />

            <Card v-if="plansAvailable">
                <CardHeader>
                    <CardTitle>Rencana menunggu persetujuan</CardTitle>
                    <CardDescription
                        >Persetujuan rencana mengalokasikan limbah. Realisasi
                        pengangkutan ditinjau terpisah setelah limbah
                        keluar.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="!pendingPlans.length"
                        class="text-sm text-muted-foreground"
                    >
                        Tidak ada rencana yang menunggu keputusan Anda.
                    </p>
                    <div
                        v-for="plan in pendingPlans"
                        :key="plan.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                    >
                        <p class="text-sm">{{ plan.description }}</p>
                        <Link
                            :href="plan.source_url"
                            class="inline-flex min-h-11 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted"
                            >Tinjau rencana</Link
                        >
                    </div>
                    <Link
                        href="/waste-management/hauling-plans"
                        class="inline-flex min-h-11 items-center text-sm font-medium text-primary"
                        >Buka semua rencana angkut</Link
                    >
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Antrean approval</CardTitle>
                    <CardDescription
                        >Pengajuan yang disetujui akan langsung mengurangi
                        limbah belum terkelola.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>No. pengajuan</TableHead>
                                <TableHead>Catatan limbah</TableHead>
                                <TableHead>Quantity</TableHead>
                                <TableHead>Diajukan oleh</TableHead>
                                <TableHead>Diajukan pada</TableHead>
                                <TableHead>Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="hauling in haulings"
                                :key="hauling.id"
                            >
                                <TableCell>
                                    <div class="font-medium">
                                        {{ hauling.hauling_number }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ hauling.hauling_date }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="font-medium">
                                        {{ hauling.waste_record.record_number }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{
                                            hauling.waste_record.waste_type
                                                ?.name || '-'
                                        }}
                                    </div>
                                </TableCell>
                                <TableCell
                                    >{{ hauling.quantity }}
                                    {{ hauling.unit }}</TableCell
                                >
                                <TableCell>{{
                                    hauling.created_by?.name || '-'
                                }}</TableCell>
                                <TableCell>{{
                                    formatDate(hauling.submitted_at)
                                }}</TableCell>
                                <TableCell>
                                    <Button as-child size="sm">
                                        <Link
                                            :href="
                                                wasteManagementRoutes.haulings.show(
                                                    hauling.id,
                                                ).url
                                            "
                                            >Review</Link
                                        >
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="haulings.length === 0">
                                <TableCell
                                    colspan="6"
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    Tidak ada pengajuan pengangkutan yang
                                    menunggu approval.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
