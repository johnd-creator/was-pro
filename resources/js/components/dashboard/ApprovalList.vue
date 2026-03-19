<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CircleCheck,
    CircleX,
    FileText,
    LoaderCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import wasteManagementRoutes from '@/routes/waste-management';

interface PendingApproval {
    id: string;
    record_number: string;
    waste_type: string;
    category: string;
    quantity: number;
    unit: string;
    submitted_by: string;
    submitted_at: string;
}

interface Props {
    approvals: PendingApproval[];
}

const props = defineProps<Props>();

const processing = ref<Record<string, boolean>>({});
const rejectDialogOpen = ref(false);
const selectedApprovalId = ref<string | null>(null);
const rejectionReason = ref('');
const rejectionError = ref<string | null>(null);
const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const selectedApproval = computed(
    () =>
        props.approvals.find(
            (approval) => approval.id === selectedApprovalId.value,
        ) ?? null,
);

function formatTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
        return 'Baru saja';
    }

    if (diffMins < 60) {
        return `${diffMins} menit lalu`;
    }

    if (diffHours < 24) {
        return `${diffHours} jam lalu`;
    }

    return `${diffDays} hari lalu`;
}

function resetMessages(): void {
    statusMessage.value = null;
    errorMessage.value = null;
}

function approvalDetailHref(approval: PendingApproval): string {
    return wasteManagementRoutes.records.show(approval.id).url;
}

function openRejectDialog(approval: PendingApproval): void {
    resetMessages();
    selectedApprovalId.value = approval.id;
    rejectionReason.value = '';
    rejectionError.value = null;
    rejectDialogOpen.value = true;
}

function closeRejectDialog(): void {
    rejectDialogOpen.value = false;
    rejectionError.value = null;
}

function approve(approval: PendingApproval): void {
    resetMessages();
    processing.value[approval.id] = true;

    router.post(
        wasteManagementRoutes.records.approve(approval.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                statusMessage.value = `Catatan ${approval.record_number} berhasil disetujui.`;
            },
            onError: () => {
                errorMessage.value = `Catatan ${approval.record_number} tidak dapat disetujui saat ini.`;
            },
            onFinish: () => {
                processing.value[approval.id] = false;
            },
        },
    );
}

function reject(): void {
    if (!selectedApproval.value) {
        return;
    }

    resetMessages();
    rejectionError.value = null;
    processing.value[selectedApproval.value.id] = true;

    router.post(
        wasteManagementRoutes.records.reject(selectedApproval.value.id).url,
        {
            rejection_reason: rejectionReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                statusMessage.value = `Catatan ${selectedApproval.value?.record_number ?? 'terpilih'} berhasil ditolak.`;
                rejectDialogOpen.value = false;
                rejectionReason.value = '';
                selectedApprovalId.value = null;
            },
            onError: (errors) => {
                rejectionError.value =
                    typeof errors.rejection_reason === 'string'
                        ? errors.rejection_reason
                        : 'Alasan penolakan belum valid. Periksa kembali lalu coba lagi.';
            },
            onFinish: () => {
                if (selectedApproval.value) {
                    processing.value[selectedApproval.value.id] = false;
                }
            },
        },
    );
}
</script>

<template>
    <div class="space-y-4">
        <Alert
            v-if="statusMessage"
            class="border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-100"
        >
            <CircleCheck
                class="h-4 w-4 !text-emerald-600 dark:!text-emerald-300"
            />
            <AlertTitle>Aksi berhasil</AlertTitle>
            <AlertDescription>{{ statusMessage }}</AlertDescription>
        </Alert>

        <Alert v-if="errorMessage" variant="destructive">
            <CircleX class="h-4 w-4" />
            <AlertTitle>Aksi gagal</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <div
            v-if="approvals.length === 0"
            class="rounded-xl border border-dashed border-border bg-muted/30 px-6 py-8 text-center"
        >
            <FileText class="mx-auto mb-3 h-8 w-8 text-muted-foreground/70" />
            <p class="font-medium text-foreground">
                Tidak ada persetujuan tertunda
            </p>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                Semua catatan yang membutuhkan keputusan sudah tertangani. Anda
                bisa kembali memantau catatan limbah aktif.
            </p>
            <Button class="mt-4" size="sm" variant="outline" as-child>
                <Link :href="wasteManagementRoutes.records.index().url">
                    Lihat catatan limbah
                </Link>
            </Button>
        </div>

        <Card
            v-for="approval in approvals"
            :key="approval.id"
            class="overflow-hidden border-slate-200/80 dark:border-slate-800"
        >
            <CardContent class="p-5">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-amber-100 px-2.5 py-1 font-mono text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-100"
                            >
                                {{ approval.record_number }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                Menunggu keputusan •
                                {{ formatTime(approval.submitted_at) }}
                            </span>
                        </div>

                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-foreground">
                                {{ approval.waste_type }}
                            </p>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ approval.category }} •
                                {{ approval.quantity }} {{ approval.unit }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Diajukan oleh {{ approval.submitted_by }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row"
                    >
                        <Button
                            size="sm"
                            :disabled="processing[approval.id]"
                            @click="approve(approval)"
                        >
                            <LoaderCircle
                                v-if="processing[approval.id]"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <CircleCheck v-else class="mr-2 h-4 w-4" />
                            Setujui cepat
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="processing[approval.id]"
                            @click="openRejectDialog(approval)"
                        >
                            <CircleX class="mr-2 h-4 w-4" />
                            Tolak
                        </Button>
                        <Button size="sm" variant="ghost" as-child>
                            <Link :href="approvalDetailHref(approval)">
                                Lihat detail
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Tolak catatan limbah</DialogTitle>
                    <DialogDescription>
                        <template v-if="selectedApproval">
                            Berikan alasan yang jelas untuk penolakan catatan
                            <span class="font-semibold">
                                {{ selectedApproval.record_number }}
                            </span>
                            agar operator dapat menindaklanjuti dengan benar.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedApproval" class="space-y-4">
                    <div
                        class="rounded-xl border border-border bg-muted/30 px-4 py-3 text-sm"
                    >
                        <p class="font-medium text-foreground">
                            {{ selectedApproval.waste_type }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{ selectedApproval.category }} •
                            {{ selectedApproval.quantity }}
                            {{ selectedApproval.unit }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Diajukan oleh {{ selectedApproval.submitted_by }}
                        </p>
                    </div>

                    <Alert v-if="rejectionError" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>Alasan penolakan belum valid</AlertTitle>
                        <AlertDescription>{{
                            rejectionError
                        }}</AlertDescription>
                    </Alert>

                    <div class="space-y-2">
                        <Label for="dashboard-rejection-reason">
                            Alasan penolakan
                        </Label>
                        <Textarea
                            id="dashboard-rejection-reason"
                            v-model="rejectionReason"
                            rows="4"
                            placeholder="Jelaskan alasan penolakan secara spesifik agar operator tahu apa yang perlu diperbaiki."
                        />
                        <p class="text-xs text-muted-foreground">
                            Minimal 10 karakter. Alasan ini akan menjadi acuan
                            tindak lanjut operator.
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeRejectDialog"
                    >
                        Batalkan
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="
                            !selectedApproval ||
                            !!processing[selectedApproval.id]
                        "
                        @click="reject"
                    >
                        <LoaderCircle
                            v-if="
                                selectedApproval &&
                                processing[selectedApproval.id]
                            "
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Tolak catatan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
