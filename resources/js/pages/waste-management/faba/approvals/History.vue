<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
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
import type { FabaAuditLog, FabaMonthlyApproval } from '@/types/faba';

defineProps<{ approvals: FabaMonthlyApproval[]; auditLogs: FabaAuditLog[] }>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Histori Approval FABA',
        href: wasteManagementRoutes.faba.approvals.history.url(),
    },
];
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Histori Approval FABA"
    >
        <Head title="Histori Approval FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Histori Approval FABA"
                description="Pantau histori approve dan reject per periode."
            />
            <div
                v-if="approvals.length === 0"
                class="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada histori approval FABA.
            </div>
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Periode</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Submitted</TableHead>
                        <TableHead>Approved</TableHead>
                        <TableHead>Rejected Note</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="approval in approvals"
                        :key="approval.id ?? `${approval.year}-${approval.month}`"
                    >
                        <TableCell
                            >{{
                                approval.period_label ||
                                `${approval.month}/${approval.year}`
                            }}</TableCell
                        >
                        <TableCell>{{ approval.status }}</TableCell>
                        <TableCell>{{
                            approval.submitted_by_user?.name || '-'
                        }}</TableCell>
                        <TableCell>{{
                            approval.approved_by_user?.name || '-'
                        }}</TableCell>
                        <TableCell>{{
                            approval.rejection_note || '-'
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div class="space-y-4">
                <Heading
                    title="Audit Trail Terbaru"
                    description="Jejak perubahan penting pada modul FABA."
                />
                <div
                    v-if="auditLogs.length === 0"
                    class="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground"
                >
                    Belum ada audit trail FABA.
                </div>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Waktu</TableHead>
                            <TableHead>Modul</TableHead>
                            <TableHead>Aksi</TableHead>
                            <TableHead>User</TableHead>
                            <TableHead>Ringkasan</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="log in auditLogs" :key="log.id">
                            <TableCell>{{ log.created_at || '-' }}</TableCell>
                            <TableCell>{{ log.module }}</TableCell>
                            <TableCell>{{ log.action }}</TableCell>
                            <TableCell>{{ log.actor?.name || '-' }}</TableCell>
                            <TableCell>{{ log.summary }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </WasteManagementLayout>
</template>
