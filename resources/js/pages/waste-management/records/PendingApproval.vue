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

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-amber-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-blue-200/15 blur-3xl"
            />

            <div class="space-y-8">
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

                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-amber-50/20 dark:via-slate-900 dark:to-amber-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-amber-700/70 uppercase"
                                >
                                    Meja Persetujuan
                                </p>
                                <Heading
                                    title="Menunggu Persetujuan"
                                    description="Tinjau catatan limbah yang membutuhkan keputusan. Anda dapat menyetujui cepat, membuka detail, atau menolak dengan alasan yang jelas."
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Total antrian: {{ wasteRecords.length }}
                                </div>
                                <div
                                    class="wm-chip-amber px-3 py-1.5 text-xs font-medium"
                                >
                                    Review aktif
                                </div>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-4 rounded-[28px] p-5"
                        >
                            <div class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Rute Cepat
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Kembali ke monitor utama
                                </p>
                            </div>

                            <Button
                                variant="outline"
                                class="w-full justify-start border-white/90 bg-white/85 shadow-sm hover:bg-white dark:bg-slate-950 dark:bg-slate-950/80"
                                as-child
                            >
                                <Link
                                    :href="
                                        wasteManagementRoutes.records.index()
                                            .url
                                    "
                                >
                                    Kembali ke semua catatan
                                </Link>
                            </Button>

                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Cara pakai
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Gunakan approve cepat untuk kasus yang
                                    jelas, lalu buka detail saat butuh validasi
                                    konteks lebih lengkap.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Antrian Review
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Antrian review
                            </h3>
                        </div>
                    </div>

                    <Card
                        class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                    >
                        <CardHeader class="pb-4">
                            <CardTitle>Antrian review</CardTitle>
                            <CardDescription>
                                Gunakan halaman ini untuk triage cepat. Buka
                                detail saat Anda membutuhkan konteks lebih
                                lengkap sebelum mengambil keputusan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="px-0 pb-0">
                            <div class="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow
                                            class="bg-slate-50/80 dark:bg-slate-900/70"
                                        >
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
                                        <TableRow
                                            v-if="wasteRecords.length === 0"
                                        >
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
                                                        Tidak ada catatan yang
                                                        menunggu persetujuan
                                                    </p>
                                                    <p
                                                        class="text-sm leading-6"
                                                    >
                                                        Semua review yang
                                                        membutuhkan tindakan
                                                        sudah diproses. Anda
                                                        dapat kembali ke daftar
                                                        catatan untuk memantau
                                                        aktivitas operasional
                                                        lainnya.
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
                                                            Kembali ke catatan
                                                            limbah
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow
                                            v-for="record in wasteRecords"
                                            :key="record.id"
                                            class="align-top transition-colors hover:bg-slate-50/80 dark:bg-slate-900/70"
                                        >
                                            <TableCell class="space-y-1">
                                                <p class="font-medium">
                                                    {{ record.record_number }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        formatDate(record.date)
                                                    }}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <p>
                                                    {{
                                                        record.waste_type
                                                            ?.name || '-'
                                                    }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        record.waste_type
                                                            ?.category?.name ||
                                                        '-'
                                                    }}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {{
                                                    Number(
                                                        record.quantity,
                                                    ).toFixed(2)
                                                }}
                                                {{ record.unit }}
                                            </TableCell>
                                            <TableCell>
                                                {{
                                                    record.submitted_by_user
                                                        ?.name || '-'
                                                }}
                                            </TableCell>
                                            <TableCell>
                                                {{
                                                    formatDate(
                                                        record.submitted_at,
                                                    )
                                                }}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    :status="record.status"
                                                    size="sm"
                                                />
                                            </TableCell>
                                            <TableCell class="space-y-3">
                                                <div
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        as-child
                                                    >
                                                        <Link
                                                            :href="
                                                                recordDetailHref(
                                                                    record,
                                                                )
                                                            "
                                                        >
                                                            Lihat detail
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        :disabled="
                                                            processing[
                                                                record.id
                                                            ]
                                                        "
                                                        @click="
                                                            approveRecord(
                                                                record,
                                                            )
                                                        "
                                                    >
                                                        <LoaderCircle
                                                            v-if="
                                                                processing[
                                                                    record.id
                                                                ]
                                                            "
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
                                                            processing[
                                                                record.id
                                                            ]
                                                        "
                                                        @click="
                                                            openRejectDialog(
                                                                record,
                                                            )
                                                        "
                                                    >
                                                        <CircleX
                                                            class="mr-2 h-4 w-4"
                                                        />
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
                                                            approvalNotesById[
                                                                record.id
                                                            ]
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
                </section>
            </div>

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
