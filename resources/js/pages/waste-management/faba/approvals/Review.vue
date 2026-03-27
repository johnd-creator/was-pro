<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaDateTime,
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaAuditLog,
    FabaClosingSnapshot,
    FabaMovement,
    FabaMonthlyApproval,
    FabaMonthlyRecap,
} from '@/types/faba';

const props = defineProps<{
    approval: FabaMonthlyApproval;
    recap: FabaMonthlyRecap;
    snapshot?: FabaClosingSnapshot | null;
    vendorBreakdown: Array<{ vendor_id: string | null; vendor_name: string; quantity: number }>;
    internalDestinationBreakdown: Array<{ internal_destination_id: string | null; internal_destination_name: string; quantity: number }>;
    purposeBreakdown: Array<{ purpose_id: string | null; purpose_name: string; quantity: number }>;
    movements: FabaMovement[];
    auditLogs: FabaAuditLog[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Approval FABA', href: wasteManagementRoutes.faba.approvals.index.url() },
    {
        title: props.approval.period_label ?? `${props.approval.month}/${props.approval.year}`,
        href: wasteManagementRoutes.faba.approvals.review([
            props.approval.year,
            props.approval.month,
        ]).url,
    },
];

const approveForm = useForm({ approval_note: '' });
const rejectForm = useForm({ rejection_note: '' });
const reopenForm = useForm({ reopen_note: '' });

function approve(): void {
    approveForm.post(wasteManagementRoutes.faba.approvals.approve([
        props.approval.year,
        props.approval.month,
    ]).url);
}

function reject(): void {
    rejectForm.post(wasteManagementRoutes.faba.approvals.reject([
        props.approval.year,
        props.approval.month,
    ]).url);
}

function reopen(): void {
    reopenForm.post(wasteManagementRoutes.faba.approvals.reopen([
        props.approval.year,
        props.approval.month,
    ]).url);
}

function submitPeriod(): void {
    router.post(wasteManagementRoutes.faba.approvals.submit.url(), {
        year: props.approval.year,
        month: props.approval.month,
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Review Approval FABA"
    >
        <Head title="Review Approval FABA" />
        <div class="space-y-6 p-6">
            <Heading
                :title="`Review ${approval.period_label ?? `${approval.month}/${approval.year}`}`"
                description="Tinjau ringkasan dan transaksi dalam satu periode."
            />
            <Card>
                <CardHeader><CardTitle>Ringkasan</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <p>
                        Status:
                        <Badge variant="secondary">
                            {{ formatFabaStatus(approval.status) }}
                        </Badge>
                    </p>
                    <p>Total produksi: {{ recap.total_production }}</p>
                    <p>Total pemanfaatan: {{ recap.total_utilization }}</p>
                    <p>Saldo awal: {{ recap.opening_balance }}</p>
                    <p>Saldo akhir: {{ recap.closing_balance }}</p>
                    <p>Status operasional: {{ approval.operational_status || approval.status }}</p>
                    <p v-if="approval.rejection_note">Catatan reject: {{ approval.rejection_note }}</p>
                </CardContent>
            </Card>
            <Card v-if="snapshot">
                <CardHeader><CardTitle>Closing Snapshot</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <p>Status snapshot: {{ snapshot.status }}</p>
                    <p>Approved at: {{ formatFabaDateTime(snapshot.approved_at) }}</p>
                    <p>Approved by: {{ snapshot.approved_by_user?.name || '-' }}</p>
                </CardContent>
            </Card>
            <Card v-if="recap.warnings.length > 0">
                <CardHeader><CardTitle>Peringatan Periode</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-2 text-sm">
                        <li v-for="warning in recap.warnings" :key="warning.code">
                            {{ warning.message }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Movement Ledger</CardTitle></CardHeader>
                <CardContent>
                    <p
                        v-if="movements.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Belum ada movement ledger pada periode ini.
                    </p>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="item in movements" :key="item.id">
                            {{ formatFabaDate(item.transaction_date) }} -
                            {{ item.display_number || item.id }} -
                            {{ formatFabaMaterial(item.material_type) }} -
                            {{ formatFabaMovementType(item.movement_type) }} -
                            {{ item.stock_effect }} {{ item.quantity }} {{ item.unit }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
            <div class="grid gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader><CardTitle>Vendor Breakdown</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="vendorBreakdown.length === 0" class="text-muted-foreground">
                            Belum ada pemanfaatan eksternal pada periode ini.
                        </p>
                        <div v-for="item in vendorBreakdown" :key="item.vendor_id ?? item.vendor_name">
                            {{ item.vendor_name }}: {{ item.quantity }} ton
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Tujuan Internal</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="internalDestinationBreakdown.length === 0" class="text-muted-foreground">
                            Belum ada pemanfaatan internal pada periode ini.
                        </p>
                        <div
                            v-for="item in internalDestinationBreakdown"
                            :key="item.internal_destination_id ?? item.internal_destination_name"
                        >
                            {{ item.internal_destination_name }}: {{ item.quantity }} ton
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Purpose / Use-case</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="purposeBreakdown.length === 0" class="text-muted-foreground">
                            Belum ada purpose yang dicatat pada periode ini.
                        </p>
                        <div v-for="item in purposeBreakdown" :key="item.purpose_id ?? item.purpose_name">
                            {{ item.purpose_name }}: {{ item.quantity }} ton
                        </div>
                    </CardContent>
                </Card>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link
                    class="text-sm text-primary underline-offset-4 hover:underline"
                    :href="wasteManagementRoutes.faba.recaps.monthly({ query: { year: approval.year, month: approval.month } }).url"
                >
                    Lihat rekap periode
                </Link>
                <Link
                    class="text-sm text-primary underline-offset-4 hover:underline"
                    :href="wasteManagementRoutes.faba.approvals.history.url()"
                >
                    Buka histori approval
                </Link>
            </div>
            <div class="grid gap-6 md:grid-cols-2">
                <Card v-if="approval.can_approve">
                    <CardHeader><CardTitle>Approve</CardTitle></CardHeader>
                    <CardContent class="space-y-4">
                        <Textarea
                            v-model="approveForm.approval_note"
                            placeholder="Catatan approve (opsional)"
                        />
                        <Button
                            @click="approve"
                            :disabled="approveForm.processing"
                            >Approve periode</Button
                        >
                    </CardContent>
                </Card>
                <Card v-if="approval.can_reject">
                    <CardHeader><CardTitle>Reject</CardTitle></CardHeader>
                    <CardContent class="space-y-4">
                        <Textarea
                            v-model="rejectForm.rejection_note"
                            placeholder="Catatan reject"
                        />
                        <Button
                            variant="destructive"
                            @click="reject"
                            :disabled="rejectForm.processing"
                            >Reject periode</Button
                        >
                    </CardContent>
                </Card>
                <Card v-if="approval.can_reopen">
                    <CardHeader><CardTitle>Reopen</CardTitle></CardHeader>
                    <CardContent class="space-y-4">
                        <Textarea
                            v-model="reopenForm.reopen_note"
                            placeholder="Alasan membuka kembali periode"
                        />
                        <Button
                            variant="outline"
                            @click="reopen"
                            :disabled="reopenForm.processing"
                            >Buka kembali periode</Button
                        >
                    </CardContent>
                </Card>
            </div>
            <div v-if="approval.can_submit" class="flex justify-end">
                <Button @click="submitPeriod">Submit periode</Button>
            </div>
            <Card>
                <CardHeader><CardTitle>Approval Log</CardTitle></CardHeader>
                <CardContent>
                    <p
                        v-if="auditLogs.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Belum ada audit trail pada periode ini.
                    </p>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="log in auditLogs" :key="log.id">
                            {{ formatFabaDateTime(log.created_at) }} - {{ log.actor?.name || 'Sistem' }} -
                            {{ log.summary }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
