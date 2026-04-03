<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CircleCheck,
    CircleX,
    FilePenLine,
    LoaderCircle,
    RotateCcw,
    Send,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import WasteRecordsController from '@/actions/App/Http/Controllers/WasteManagement/WasteRecordsController';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import StatusBadge from '@/components/waste-management/StatusBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import type { BreadcrumbItem } from '@/types';

interface WasteRecord {
    id: string;
    record_number: string;
    date: string;
    expiry_date: string | null;
    quantity: number;
    unit: string;
    source: string | null;
    description: string | null;
    notes: string | null;
    status: 'draft' | 'pending_review' | 'approved' | 'rejected';
    rejection_reason: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    approval_notes: string | null;
    waste_type?: {
        name: string;
        code: string;
        category?: {
            name: string;
        };
        characteristic?: {
            name: string;
            is_hazardous: boolean;
        };
    };
    created_by?: {
        name: string;
    };
    submitted_by_user?: {
        name: string;
    };
    approved_by_user?: {
        name: string;
    };
}

type Props = {
    wasteRecord: WasteRecord;
};

const props = defineProps<Props>();
const page = usePage();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Catatan Limbah',
        href: WasteRecordsController.index().url,
    },
    {
        title: props.wasteRecord.record_number,
        href: WasteRecordsController.show(props.wasteRecord.id).url,
    },
];

const submitForm = useForm({});
const reviewForm = useForm({
    approval_notes: props.wasteRecord.approval_notes ?? '',
    rejection_reason: props.wasteRecord.rejection_reason ?? '',
});
const returnForm = useForm({});

const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const currentUser = computed(
    () =>
        page.props.auth?.user as
            | { is_super_admin?: boolean; permissions?: string[] }
            | undefined,
);

const canApprove = computed(() => {
    if (currentUser.value?.is_super_admin) {
        return true;
    }

    return (
        currentUser.value?.permissions?.includes('waste_records.approve') ??
        false
    );
});

const canReject = computed(() => {
    if (currentUser.value?.is_super_admin) {
        return true;
    }

    return (
        currentUser.value?.permissions?.includes('waste_records.reject') ??
        false
    );
});

const canSubmit = computed(() => {
    if (currentUser.value?.is_super_admin) {
        return true;
    }

    return (
        currentUser.value?.permissions?.includes('waste_records.submit') ??
        false
    );
});

const canEdit = computed(
    () =>
        props.wasteRecord.status === 'draft' ||
        props.wasteRecord.status === 'rejected',
);

const workflowSummary = computed(() => {
    const summaries = {
        draft: {
            title: 'Catatan masih dalam draf',
            description:
                'Lengkapi informasi catatan lalu ajukan saat sudah siap untuk ditinjau.',
        },
        pending_review: {
            title: 'Catatan sedang menunggu keputusan',
            description:
                'Approver dapat memberikan catatan persetujuan atau alasan penolakan langsung dari halaman ini.',
        },
        approved: {
            title: 'Catatan sudah disetujui',
            description:
                'Catatan ini siap dipantau untuk tindak lanjut operasional berikutnya, termasuk transportasi.',
        },
        rejected: {
            title: 'Catatan ditolak dan perlu diperbaiki',
            description:
                'Periksa alasan penolakan, kembalikan ke draf, lalu perbarui data sebelum diajukan lagi.',
        },
    };

    return summaries[props.wasteRecord.status];
});

function resetMessages(): void {
    statusMessage.value = null;
    errorMessage.value = null;
}

function formatDate(date: string | null): string {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function submitForApproval(): void {
    resetMessages();
    submitForm.post(WasteRecordsController.submit(props.wasteRecord.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            statusMessage.value =
                'Catatan berhasil diajukan untuk ditinjau oleh approver.';
        },
        onError: () => {
            errorMessage.value =
                'Catatan belum dapat diajukan. Periksa kembali data lalu coba lagi.';
        },
    });
}

function approveRecord(): void {
    resetMessages();
    reviewForm.clearErrors('rejection_reason');
    reviewForm.post(WasteRecordsController.approve(props.wasteRecord.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            statusMessage.value = 'Catatan berhasil disetujui.';
        },
        onError: () => {
            errorMessage.value =
                'Persetujuan gagal diproses. Periksa kembali data lalu coba lagi.';
        },
    });
}

function rejectRecord(): void {
    resetMessages();
    reviewForm.clearErrors('approval_notes');
    reviewForm.post(WasteRecordsController.reject(props.wasteRecord.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            statusMessage.value =
                'Catatan berhasil ditolak dan alasan penolakan telah disimpan.';
        },
        onError: () => {
            errorMessage.value =
                'Penolakan belum dapat diproses. Periksa alasan penolakan lalu coba lagi.';
        },
    });
}

function returnToDraft(): void {
    resetMessages();
    returnForm.post(
        WasteRecordsController.returnToDraft(props.wasteRecord.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                statusMessage.value =
                    'Catatan berhasil dikembalikan ke draf untuk diperbaiki.';
            },
            onError: () => {
                errorMessage.value =
                    'Catatan belum dapat dikembalikan ke draf saat ini.';
            },
        },
    );
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Detail Catatan Limbah"
    >
        <Head :title="`${wasteRecord.record_number} - Catatan Limbah`" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-sky-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
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
                    class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]"
                >
                    <Card
                        class="overflow-hidden rounded-[30px] border-slate-200/80 bg-linear-to-br from-white via-slate-50/80 to-sky-50/20 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:border-slate-800"
                    >
                        <CardContent class="space-y-6 p-6 lg:p-8">
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="space-y-4">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700 dark:bg-sky-900/40 dark:text-sky-200"
                                        >
                                            Detail operasional
                                        </span>
                                        <StatusBadge
                                            :status="wasteRecord.status"
                                            size="lg"
                                        />
                                    </div>
                                    <Heading
                                        :title="wasteRecord.record_number"
                                        description="Pantau status catatan, tinjau data inti, dan lakukan tindakan workflow yang diperlukan dari satu halaman."
                                    />
                                    <p
                                        class="-mt-5 text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ workflowSummary.description }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:flex-wrap"
                            >
                                <Button variant="outline" as-child>
                                    <Link
                                        :href="
                                            WasteRecordsController.index().url
                                        "
                                    >
                                        Kembali ke daftar
                                    </Link>
                                </Button>
                                <Button v-if="canEdit" as-child>
                                    <Link
                                        :href="
                                            WasteRecordsController.edit(
                                                wasteRecord.id,
                                            ).url
                                        "
                                    >
                                        <FilePenLine class="mr-2 h-4 w-4" />
                                        Edit catatan
                                    </Link>
                                </Button>
                                <Button
                                    v-if="
                                        wasteRecord.status === 'draft' &&
                                        canSubmit
                                    "
                                    :disabled="submitForm.processing"
                                    @click="submitForApproval"
                                >
                                    <LoaderCircle
                                        v-if="submitForm.processing"
                                        class="mr-2 h-4 w-4 animate-spin"
                                    />
                                    <Send v-else class="mr-2 h-4 w-4" />
                                    Ajukan untuk ditinjau
                                </Button>
                                <Button
                                    v-if="
                                        wasteRecord.status === 'rejected' &&
                                        canSubmit
                                    "
                                    variant="secondary"
                                    :disabled="returnForm.processing"
                                    @click="returnToDraft"
                                >
                                    <LoaderCircle
                                        v-if="returnForm.processing"
                                        class="mr-2 h-4 w-4 animate-spin"
                                    />
                                    <RotateCcw v-else class="mr-2 h-4 w-4" />
                                    Kembalikan ke draf
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="overflow-hidden rounded-[30px] border-slate-200/80 bg-slate-950 text-slate-50 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.45)] dark:border-slate-800"
                    >
                        <CardHeader class="space-y-3">
                            <CardTitle class="text-xl text-white">
                                Ringkasan workflow
                            </CardTitle>
                            <CardDescription class="text-slate-300">
                                Status saat ini dan jejak keputusan terakhir
                                untuk catatan ini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <div
                                class="rounded-xl border border-white/10 bg-white p-4 dark:bg-slate-950/5"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-slate-400 uppercase"
                                >
                                    Kondisi saat ini
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold text-white"
                                >
                                    {{ workflowSummary.title }}
                                </p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded-xl border border-white/10 bg-white p-4 dark:bg-slate-950/5"
                                >
                                    <p class="text-xs text-slate-400">
                                        Diajukan pada
                                    </p>
                                    <p class="mt-2 font-medium text-white">
                                        {{
                                            formatDate(wasteRecord.submitted_at)
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl border border-white/10 bg-white p-4 dark:bg-slate-950/5"
                                >
                                    <p class="text-xs text-slate-400">
                                        Diputuskan pada
                                    </p>
                                    <p class="mt-2 font-medium text-white">
                                        {{
                                            formatDate(wasteRecord.approved_at)
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="wasteRecord.rejection_reason"
                                class="rounded-xl border border-red-900/40 bg-red-950/30 p-4"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-red-200/80 uppercase"
                                >
                                    Alasan penolakan
                                </p>
                                <p class="mt-2 leading-6 text-red-100">
                                    {{ wasteRecord.rejection_reason }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <Card
                    v-if="
                        wasteRecord.status === 'pending_review' &&
                        (canApprove || canReject)
                    "
                    class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                >
                    <CardHeader>
                        <CardTitle>Panel review</CardTitle>
                        <CardDescription>
                            Berikan catatan persetujuan atau tolak catatan
                            dengan alasan yang jelas agar operator dapat
                            menindaklanjuti dengan benar.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-6 lg:grid-cols-2">
                        <div
                            v-if="canApprove"
                            class="space-y-4 rounded-xl border border-border p-4"
                        >
                            <div class="space-y-1">
                                <h3 class="font-medium text-foreground">
                                    Setujui catatan
                                </h3>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    Catatan persetujuan bersifat opsional,
                                    tetapi dapat membantu tim operasional
                                    memahami konteks keputusan Anda.
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="approval_notes"
                                    >Catatan persetujuan</Label
                                >
                                <Textarea
                                    id="approval_notes"
                                    v-model="reviewForm.approval_notes"
                                    rows="4"
                                    placeholder="Tambahkan catatan persetujuan bila diperlukan."
                                />
                            </div>
                            <Button
                                :disabled="reviewForm.processing"
                                @click="approveRecord"
                            >
                                <LoaderCircle
                                    v-if="reviewForm.processing"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Setujui catatan
                            </Button>
                        </div>

                        <div
                            v-if="canReject"
                            class="space-y-4 rounded-xl border border-border p-4"
                        >
                            <div class="space-y-1">
                                <h3 class="font-medium text-foreground">
                                    Tolak catatan
                                </h3>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    Alasan penolakan wajib jelas dan spesifik
                                    agar perbaikan berikutnya tidak
                                    membingungkan operator.
                                </p>
                            </div>

                            <Alert
                                v-if="reviewForm.errors.rejection_reason"
                                variant="destructive"
                            >
                                <AlertCircle class="h-4 w-4" />
                                <AlertTitle
                                    >Alasan penolakan belum valid</AlertTitle
                                >
                                <AlertDescription>
                                    {{ reviewForm.errors.rejection_reason }}
                                </AlertDescription>
                            </Alert>

                            <div class="space-y-2">
                                <Label for="rejection_reason"
                                    >Alasan penolakan</Label
                                >
                                <Textarea
                                    id="rejection_reason"
                                    v-model="reviewForm.rejection_reason"
                                    rows="4"
                                    placeholder="Jelaskan apa yang perlu diperbaiki sebelum catatan dapat diajukan kembali."
                                />
                                <p class="text-xs text-muted-foreground">
                                    Minimal 10 karakter dan sebaiknya
                                    menyebutkan bagian data yang perlu
                                    diperbaiki.
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                :disabled="
                                    reviewForm.processing ||
                                    reviewForm.rejection_reason.trim().length <
                                        10
                                "
                                @click="rejectRecord"
                            >
                                <LoaderCircle
                                    v-if="reviewForm.processing"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Tolak catatan
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Detail Catatan
                            </p>
                            <h3
                                class="mt-2 flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                <span class="h-px w-8 bg-slate-300" />
                                Informasi inti dan jejak keputusan
                            </h3>
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <Card
                            class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                        >
                            <CardHeader>
                                <CardTitle>Informasi catatan</CardTitle>
                                <CardDescription>
                                    Ringkasan data inti yang memengaruhi
                                    klasifikasi dan tindak lanjut catatan
                                    limbah.
                                </CardDescription>
                            </CardHeader>
                            <CardContent
                                class="grid gap-4 text-sm sm:grid-cols-2"
                            >
                                <div>
                                    <p class="font-medium">
                                        Tanggal pencatatan
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ formatDate(wasteRecord.date) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">
                                        Tanggal batas simpan
                                    </p>
                                    <div class="mt-1">
                                        <ExpiryBadge
                                            :expiry-date="
                                                wasteRecord.expiry_date
                                            "
                                        />
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium">Jenis limbah</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            wasteRecord.waste_type?.name || '-'
                                        }}
                                        ({{
                                            wasteRecord.waste_type?.code || '-'
                                        }})
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">Kategori</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            wasteRecord.waste_type?.category
                                                ?.name || '-'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">Karakteristik</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            wasteRecord.waste_type
                                                ?.characteristic?.name || '-'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">Jumlah</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            Number(
                                                wasteRecord.quantity,
                                            ).toFixed(2)
                                        }}
                                        {{ wasteRecord.unit }}
                                    </p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="font-medium">Sumber / lokasi</p>
                                    <p class="text-muted-foreground">
                                        {{ wasteRecord.source || '-' }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                        >
                            <CardHeader>
                                <CardTitle>Jejak keputusan</CardTitle>
                                <CardDescription>
                                    Siapa yang membuat, mengajukan, dan
                                    memutuskan catatan ini.
                                </CardDescription>
                            </CardHeader>
                            <CardContent
                                class="grid gap-4 text-sm sm:grid-cols-2"
                            >
                                <div>
                                    <p class="font-medium">Dibuat oleh</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            wasteRecord.created_by?.name || '-'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">Diajukan oleh</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            wasteRecord.submitted_by_user
                                                ?.name || '-'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">
                                        Disetujui / ditolak oleh
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{
                                            wasteRecord.approved_by_user
                                                ?.name || '-'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">Waktu keputusan</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            formatDate(wasteRecord.approved_at)
                                        }}
                                    </p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="font-medium">
                                        Catatan persetujuan
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ wasteRecord.approval_notes || '-' }}
                                    </p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="font-medium">Alasan penolakan</p>
                                    <p class="text-muted-foreground">
                                        {{
                                            wasteRecord.rejection_reason || '-'
                                        }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <Card
                    class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                >
                    <CardHeader>
                        <CardTitle>Deskripsi dan catatan internal</CardTitle>
                        <CardDescription>
                            Konteks tambahan untuk membantu review dan tindak
                            lanjut operasional.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 text-sm lg:grid-cols-2">
                        <div>
                            <p class="font-medium">Deskripsi</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                {{ wasteRecord.description || '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="font-medium">Catatan internal</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                {{ wasteRecord.notes || '-' }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
