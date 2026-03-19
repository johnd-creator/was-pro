<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import StatusBadge from '@/components/waste-management/StatusBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface WasteRecord {
    id: string;
    record_number: string;
    date: string;
    quantity: number;
    unit: string;
    status: 'draft' | 'pending_review' | 'approved' | 'rejected';
    waste_type?: {
        name: string;
        code: string;
        category?: {
            name: string;
        };
    };
    submitted_by_user?: {
        name: string;
    };
    submitted_at?: string;
}

type Props = {
    wasteRecords: WasteRecord[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Catatan Limbah',
        href: wasteManagementRoutes.records.index().url,
    },
    {
        title: 'Menunggu Persetujuan',
        href: wasteManagementRoutes.records.pendingApproval().url,
    },
];

const approvalNotesById = ref<Record<string, string>>({});
const processing = ref<Record<string, boolean>>({});
const rejectDialogOpen = ref(false);
const selectedRecordId = ref<string | null>(null);
const rejectionReason = ref('');
const rejectionError = ref<string | null>(null);
const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const selectedRecord = computed(
    () =>
        props.wasteRecords.find(
            (record) => record.id === selectedRecordId.value,
        ) ?? null,
);

function formatDate(dateString: string | undefined): string {
    if (!dateString) {
        return '-';
    }

    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function resetMessages(): void {
    statusMessage.value = null;
    errorMessage.value = null;
}

function recordDetailHref(record: WasteRecord): string {
    return wasteManagementRoutes.records.show(record.id).url;
}

function approveRecord(record: WasteRecord): void {
    resetMessages();
    processing.value[record.id] = true;

    router.post(
        wasteManagementRoutes.records.approve(record.id).url,
        {
            approval_notes: approvalNotesById.value[record.id] || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                statusMessage.value = `Catatan ${record.record_number} berhasil disetujui.`;
            },
            onError: () => {
                errorMessage.value = `Catatan ${record.record_number} tidak dapat disetujui saat ini.`;
            },
            onFinish: () => {
                processing.value[record.id] = false;
            },
        },
    );
}

function openRejectDialog(record: WasteRecord): void {
    resetMessages();
    selectedRecordId.value = record.id;
    rejectionReason.value = '';
    rejectionError.value = null;
    rejectDialogOpen.value = true;
}

function closeRejectDialog(): void {
    rejectDialogOpen.value = false;
    rejectionError.value = null;
}

function rejectRecord(): void {
    if (!selectedRecord.value) {
        return;
    }

    resetMessages();
    rejectionError.value = null;
    processing.value[selectedRecord.value.id] = true;

    router.post(
        wasteManagementRoutes.records.reject(selectedRecord.value.id).url,
        {
            rejection_reason: rejectionReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                statusMessage.value = `Catatan ${selectedRecord.value?.record_number ?? 'terpilih'} berhasil ditolak.`;
                rejectDialogOpen.value = false;
                rejectionReason.value = '';
                selectedRecordId.value = null;
            },
            onError: (errors) => {
                rejectionError.value =
                    typeof errors.rejection_reason === 'string'
                        ? errors.rejection_reason
                        : 'Alasan penolakan belum valid. Periksa kembali lalu coba lagi.';
            },
            onFinish: () => {
                if (selectedRecord.value) {
                    processing.value[selectedRecord.value.id] = false;
                }
            },
        },
    );
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Menunggu Persetujuan"
    >
        <Head title="Menunggu Persetujuan - Catatan Limbah" />

        <div class="space-y-6 p-6">
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
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <Heading
                    title="Menunggu Persetujuan"
                    description="Tinjau catatan limbah yang membutuhkan keputusan. Anda dapat menyetujui cepat, membuka detail, atau menolak dengan alasan yang jelas."
                />
                <Button variant="outline" as-child>
                    <Link :href="wasteManagementRoutes.records.index().url">
                        Kembali ke semua catatan
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Antrian review</CardTitle>
                    <CardDescription>
                        Gunakan halaman ini untuk triage cepat. Buka detail saat
                        Anda membutuhkan konteks lebih lengkap sebelum mengambil
                        keputusan.
                    </CardDescription>
                </CardHeader>
                <CardContent class="px-0 pb-0">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Catatan</TableHead>
                                    <TableHead>Jenis limbah</TableHead>
                                    <TableHead>Jumlah</TableHead>
                                    <TableHead>Diajukan oleh</TableHead>
                                    <TableHead>Diajukan pada</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="w-[360px]"
                                        >Tindakan</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="wasteRecords.length === 0">
                                    <TableCell
                                        :colspan="7"
                                        class="py-12 text-center"
                                    >
                                        <div
                                            class="mx-auto flex max-w-lg flex-col items-center gap-3 text-muted-foreground"
                                        >
                                            <FileText
                                                class="h-8 w-8 opacity-60"
                                            />
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                Tidak ada catatan yang menunggu
                                                persetujuan
                                            </p>
                                            <p class="text-sm leading-6">
                                                Semua review yang membutuhkan
                                                tindakan sudah diproses. Anda
                                                dapat kembali ke daftar catatan
                                                untuk memantau aktivitas
                                                operasional lainnya.
                                            </p>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link
                                                    :href="
                                                        wasteManagementRoutes.records.index()
                                                            .url
                                                    "
                                                >
                                                    Kembali ke catatan limbah
                                                </Link>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <TableRow
                                    v-for="record in wasteRecords"
                                    :key="record.id"
                                    class="align-top"
                                >
                                    <TableCell class="space-y-1">
                                        <p class="font-medium">
                                            {{ record.record_number }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ formatDate(record.date) }}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <p>
                                            {{ record.waste_type?.name || '-' }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                record.waste_type?.category
                                                    ?.name || '-'
                                            }}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {{ Number(record.quantity).toFixed(2) }}
                                        {{ record.unit }}
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            record.submitted_by_user?.name ||
                                            '-'
                                        }}
                                    </TableCell>
                                    <TableCell>
                                        {{ formatDate(record.submitted_at) }}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            :status="record.status"
                                            size="sm"
                                        />
                                    </TableCell>
                                    <TableCell class="space-y-3">
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link
                                                    :href="
                                                        recordDetailHref(record)
                                                    "
                                                >
                                                    Lihat detail
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                :disabled="
                                                    processing[record.id]
                                                "
                                                @click="approveRecord(record)"
                                            >
                                                <LoaderCircle
                                                    v-if="processing[record.id]"
                                                    class="mr-2 h-4 w-4 animate-spin"
                                                />
                                                <CircleCheck
                                                    v-else
                                                    class="mr-2 h-4 w-4"
                                                />
                                                Setujui cepat
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                :disabled="
                                                    processing[record.id]
                                                "
                                                @click="
                                                    openRejectDialog(record)
                                                "
                                            >
                                                <CircleX class="mr-2 h-4 w-4" />
                                                Tolak
                                            </Button>
                                        </div>
                                        <div class="space-y-2">
                                            <Label
                                                :for="`approval-notes-${record.id}`"
                                            >
                                                Catatan persetujuan
                                            </Label>
                                            <Textarea
                                                :id="`approval-notes-${record.id}`"
                                                v-model="
                                                    approvalNotesById[record.id]
                                                "
                                                rows="3"
                                                placeholder="Tambahkan catatan persetujuan bila ada konteks yang perlu diketahui operator."
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Dialog v-model:open="rejectDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Tolak catatan limbah</DialogTitle>
                        <DialogDescription>
                            <template v-if="selectedRecord">
                                Berikan alasan yang jelas untuk penolakan
                                catatan
                                <span class="font-semibold">
                                    {{ selectedRecord.record_number }}
                                </span>
                                agar operator tahu apa yang harus diperbaiki.
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedRecord" class="space-y-4">
                        <div
                            class="rounded-xl border border-border bg-muted/30 px-4 py-3 text-sm"
                        >
                            <p class="font-medium text-foreground">
                                {{ selectedRecord.waste_type?.name || '-' }}
                            </p>
                            <p class="mt-1 text-muted-foreground">
                                {{
                                    selectedRecord.waste_type?.category?.name ||
                                    '-'
                                }}
                                •
                                {{ Number(selectedRecord.quantity).toFixed(2) }}
                                {{ selectedRecord.unit }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Diajukan oleh
                                {{
                                    selectedRecord.submitted_by_user?.name ||
                                    '-'
                                }}
                            </p>
                        </div>

                        <Alert v-if="rejectionError" variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertTitle
                                >Alasan penolakan belum valid</AlertTitle
                            >
                            <AlertDescription>{{
                                rejectionError
                            }}</AlertDescription>
                        </Alert>

                        <div class="space-y-2">
                            <Label for="pending-approval-rejection-reason">
                                Alasan penolakan
                            </Label>
                            <Textarea
                                id="pending-approval-rejection-reason"
                                v-model="rejectionReason"
                                rows="4"
                                placeholder="Jelaskan apa yang harus diperbaiki sebelum catatan diajukan kembali."
                            />
                            <p class="text-xs text-muted-foreground">
                                Minimal 10 karakter dan sebaiknya menyebutkan
                                bagian data yang perlu diperbaiki.
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
                                !selectedRecord ||
                                !!processing[selectedRecord.id]
                            "
                            @click="rejectRecord"
                        >
                            <LoaderCircle
                                v-if="
                                    selectedRecord &&
                                    processing[selectedRecord.id]
                                "
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Tolak catatan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </WasteManagementLayout>
</template>
