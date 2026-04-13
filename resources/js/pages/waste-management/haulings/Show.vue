<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import WasteHaulingsController from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import type { BreadcrumbItem } from '@/types';

interface HaulingItem {
    id: string;
    hauling_number: string;
    hauling_date: string;
    quantity: number;
    unit: string;
    notes: string | null;
    status: string;
    status_label: string;
    submitted_at: string | null;
    approved_at: string | null;
    approval_notes: string | null;
    rejection_reason: string | null;
    created_by: { name: string } | null;
    submitted_by: { name: string } | null;
    approved_by: { name: string } | null;
}

interface RecordItem {
    id: string;
    record_number: string;
    expiry_date: string | null;
    quantity: number;
    unit: string;
    approved_hauled_quantity: number;
    remaining_quantity: number;
    operational_status_label: string;
    waste_type: {
        name: string | null;
        category: { name: string | null } | null;
    };
    hauling_history: HaulingItem[];
}

const props = defineProps<{
    hauling: HaulingItem;
    record: RecordItem;
    abilities: {
        can_approve: boolean;
        can_reject: boolean;
        can_cancel: boolean;
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Pengangkutan Limbah', href: WasteHaulingsController.index().url },
    {
        title: props.hauling.hauling_number,
        href: WasteHaulingsController.show(props.hauling.id).url,
    },
];

const approveForm = useForm({
    approval_notes: props.hauling.approval_notes ?? '',
});

const rejectForm = useForm({
    rejection_reason: props.hauling.rejection_reason ?? '',
});

const isPending = computed(() => props.hauling.status === 'pending_approval');

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

function approve(): void {
    approveForm.post(WasteHaulingsController.approve(props.hauling.id).url, {
        preserveScroll: true,
    });
}

function reject(): void {
    rejectForm.post(WasteHaulingsController.reject(props.hauling.id).url, {
        preserveScroll: true,
    });
}

function cancel(): void {
    useForm({}).post(WasteHaulingsController.cancel(props.hauling.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Detail Pengangkutan"
    >
        <Head :title="`${hauling.hauling_number} - Pengangkutan Limbah`" />

        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <Heading
                title="Detail Pengangkutan"
                description="Tinjau pengajuan, status approval, dan dampaknya terhadap sisa limbah."
            />

            <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ hauling.hauling_number }}</CardTitle>
                        <CardDescription>{{
                            hauling.status_label
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <span class="text-muted-foreground"
                                >Tanggal angkut:</span
                            >
                            {{ hauling.hauling_date }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Quantity:</span>
                            {{ hauling.quantity }} {{ hauling.unit }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Operator:</span>
                            {{ hauling.created_by?.name || '-' }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Diajukan:</span>
                            {{ formatDate(hauling.submitted_at) }}
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Diputuskan:</span
                            >
                            {{ formatDate(hauling.approved_at) }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Catatan:</span>
                            {{ hauling.notes || '-' }}
                        </div>
                        <div v-if="hauling.approval_notes">
                            <span class="text-muted-foreground"
                                >Catatan approval:</span
                            >
                            {{ hauling.approval_notes }}
                        </div>
                        <div v-if="hauling.rejection_reason">
                            <span class="text-muted-foreground"
                                >Alasan penolakan:</span
                            >
                            {{ hauling.rejection_reason }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ringkasan catatan limbah</CardTitle>
                        <CardDescription>{{
                            record.record_number
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <span class="text-muted-foreground">Jenis:</span>
                            {{ record.waste_type.name || '-' }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Kategori:</span>
                            {{ record.waste_type.category?.name || '-' }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground"
                                >Masa simpan:</span
                            >
                            <ExpiryBadge
                                :expiry-date="record.expiry_date"
                                size="sm"
                            />
                        </div>
                        <div>
                            <span class="text-muted-foreground">Total:</span>
                            {{ record.quantity }} {{ record.unit }}
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Sudah diangkut:</span
                            >
                            {{ record.approved_hauled_quantity }}
                            {{ record.unit }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Sisa:</span>
                            {{ record.remaining_quantity }} {{ record.unit }}
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Status operasional:</span
                            >
                            {{ record.operational_status_label }}
                        </div>
                        <Button as-child variant="outline" size="sm">
                            <Link
                                :href="`/waste-management/records/${record.id}`"
                                >Buka catatan limbah</Link
                            >
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <div
                v-if="
                    isPending && (abilities.can_approve || abilities.can_reject)
                "
                class="grid gap-6 lg:grid-cols-2"
            >
                <Card v-if="abilities.can_approve">
                    <CardHeader>
                        <CardTitle>Setujui pengajuan</CardTitle>
                        <CardDescription
                            >Approval langsung mengurangi backlog
                            limbah.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="approval_notes">Catatan approval</Label>
                            <Textarea
                                id="approval_notes"
                                v-model="approveForm.approval_notes"
                                rows="4"
                            />
                            <p
                                v-if="approveForm.errors.approval_notes"
                                class="text-sm text-destructive"
                            >
                                {{ approveForm.errors.approval_notes }}
                            </p>
                        </div>
                        <Button
                            :disabled="approveForm.processing"
                            @click="approve"
                            >Setujui</Button
                        >
                    </CardContent>
                </Card>

                <Card v-if="abilities.can_reject">
                    <CardHeader>
                        <CardTitle>Tolak pengajuan</CardTitle>
                        <CardDescription
                            >Gunakan alasan yang jelas agar operator bisa
                            menindaklanjuti.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="rejection_reason"
                                >Alasan penolakan</Label
                            >
                            <Textarea
                                id="rejection_reason"
                                v-model="rejectForm.rejection_reason"
                                rows="4"
                            />
                            <p
                                v-if="rejectForm.errors.rejection_reason"
                                class="text-sm text-destructive"
                            >
                                {{ rejectForm.errors.rejection_reason }}
                            </p>
                        </div>
                        <Button
                            :disabled="rejectForm.processing"
                            variant="destructive"
                            @click="reject"
                            >Tolak</Button
                        >
                    </CardContent>
                </Card>
            </div>

            <Card v-if="abilities.can_cancel">
                <CardHeader>
                    <CardTitle>Batalkan pengajuan</CardTitle>
                    <CardDescription
                        >Hanya operator pemilik pengajuan yang bisa membatalkan
                        sebelum diputuskan.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Button
                        variant="outline"
                        :disabled="!isPending"
                        @click="cancel"
                        >Batalkan</Button
                    >
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Riwayat pengangkutan catatan ini</CardTitle>
                    <CardDescription
                        >Timeline ini menunjukkan setiap pengajuan pengangkutan
                        pada catatan limbah yang sama.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="history in record.hauling_history"
                        :key="history.id"
                        class="rounded-lg border p-4 text-sm"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div class="font-medium">
                                {{ history.hauling_number }}
                            </div>
                            <div class="text-muted-foreground">
                                {{ history.status_label }}
                            </div>
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            {{ history.hauling_date }} • {{ history.quantity }}
                            {{ history.unit }}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
