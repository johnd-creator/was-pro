<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
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

defineProps<{ haulings: HaulingItem[] }>();

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

        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <Heading
                title="Approval Pengangkutan"
                description="Supervisor meninjau dan menyetujui pengajuan angkut dari operator."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Antrean approval</CardTitle>
                    <CardDescription
                        >Pengajuan yang disetujui akan langsung mengurangi
                        backlog limbah.</CardDescription
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
