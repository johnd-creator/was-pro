<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaAuditLog,
    FabaMonthlyApproval,
    FabaMonthlyRecap,
    FabaProductionEntry,
    FabaUtilizationEntry,
} from '@/types/faba';

const props = defineProps<{
    approval: FabaMonthlyApproval;
    recap: FabaMonthlyRecap;
    productionEntries: FabaProductionEntry[];
    utilizationEntries: FabaUtilizationEntry[];
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
                    <p>Status: {{ approval.status }}</p>
                    <p>Total produksi: {{ recap.total_production }}</p>
                    <p>Total pemanfaatan: {{ recap.total_utilization }}</p>
                    <p>Saldo awal: {{ recap.opening_balance }}</p>
                    <p>Saldo akhir: {{ recap.closing_balance }}</p>
                    <p v-if="approval.rejection_note">Catatan reject: {{ approval.rejection_note }}</p>
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
                <CardHeader><CardTitle>Produksi</CardTitle></CardHeader>
                <CardContent>
                    <p
                        v-if="productionEntries.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Tidak ada transaksi produksi pada periode ini.
                    </p>
                    <ul v-else class="space-y-2">
                        <li v-for="item in productionEntries" :key="item.id">
                            {{ item.transaction_date }} -
                            {{ item.entry_number }} - {{ item.material_type }} -
                            {{ item.quantity }} {{ item.unit }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Pemanfaatan</CardTitle></CardHeader>
                <CardContent>
                    <p
                        v-if="utilizationEntries.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Tidak ada transaksi pemanfaatan pada periode ini.
                    </p>
                    <ul v-else class="space-y-2">
                        <li v-for="item in utilizationEntries" :key="item.id">
                            {{ item.transaction_date }} -
                            {{ item.entry_number }} - {{ item.quantity }}
                            {{ item.unit }} - {{ item.vendor?.name || '-' }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
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
                            {{ log.created_at }} - {{ log.actor?.name || 'Sistem' }} -
                            {{ log.summary }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
