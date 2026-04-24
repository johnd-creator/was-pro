<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarClock,
    FileText,
    PackageOpen,
    ShieldCheck,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaUtilizationMovement } from '@/types/faba';

const props = defineProps<{ entry: FabaUtilizationMovement }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pemanfaatan FABA',
        href: wasteManagementRoutes.faba.utilization.index.url(),
    },
    {
        title: props.entry.display_number,
        href: wasteManagementRoutes.faba.utilization.show(props.entry.id).url,
    },
];

const approveForm = useForm({ approval_note: '' });
const rejectForm = useForm({ rejection_note: '' });

function approve(): void {
    approveForm.post(
        wasteManagementRoutes.faba.movements.approve(props.entry.id).url,
    );
}

function reject(): void {
    rejectForm.post(wasteManagementRoutes.faba.movements.reject(props.entry.id).url);
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Detail Pemanfaatan FABA"
    >
        <Head title="Detail Pemanfaatan FABA" />
        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-20 right-0 -z-10 h-56 w-56 rounded-full bg-amber-200/15 blur-3xl"
            />

            <div class="mx-auto max-w-5xl space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-emerald-50/20 dark:via-slate-900 dark:to-emerald-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                            >
                                Utilization Detail
                            </p>
                            <Heading
                                :title="entry.display_number"
                                description="Detail transaksi pemanfaatan FABA dengan konteks pihak, dokumen, dan status periode."
                            />
                            <div class="flex flex-wrap gap-3">
                                <Badge
                                    variant="secondary"
                                    class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        formatFabaStatus(
                                            entry.effective_status ??
                                                entry.approval_status,
                                        )
                                    }}
                                </Badge>
                                <Badge
                                    v-if="entry.locked"
                                    variant="secondary"
                                    class="rounded-full border border-amber-200/80 bg-amber-50/90 text-amber-800"
                                >
                                    Periode terkunci
                                </Badge>
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    {{ formatFabaDate(entry.transaction_date) }}
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div class="wm-hero-stat-card wm-hero-stat-neutral">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Tipe
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                >
                                    {{
                                        formatFabaMovementType(
                                            entry.movement_type,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="wm-hero-stat-card wm-hero-stat-emerald">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
                                >
                                    Quantity
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                >
                                    {{ entry.quantity }} {{ entry.unit }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <Card
                    v-if="entry.duplicate_warning"
                    class="rounded-[28px] border-amber-200/80 bg-amber-50/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)]"
                >
                    <CardContent class="p-5 text-sm leading-6 text-amber-950">
                        {{ entry.duplicate_warning.message }}
                    </CardContent>
                </Card>

                <Card
                    class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                >
                    <CardHeader
                        ><CardTitle>Informasi Utama</CardTitle></CardHeader
                    >
                    <CardContent class="grid gap-4 lg:grid-cols-2">
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <div class="flex items-center gap-3">
                                <CalendarClock
                                    class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tanggal
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaDate(
                                                entry.transaction_date,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <div class="flex items-center gap-3">
                                <PackageOpen
                                    class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Material
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaMaterial(
                                                entry.material_type,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <div class="flex items-center gap-3">
                                <ArrowLeftRight
                                    class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tipe
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaMovementType(
                                                entry.movement_type,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <div class="flex items-center gap-3">
                                <ShieldCheck
                                    class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Status Closing
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaStatus(
                                                entry.period_status ??
                                                    entry.approval_status,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Vendor
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.vendor?.name || '-' }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Tujuan internal
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.internal_destination?.name || '-' }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Dokumen
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.document_number || '-' }}
                            </p>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ formatFabaDate(entry.document_date) }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Use-case
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.purpose?.name || '-' }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Lampiran
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.attachment_path || '-' }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Jumlah
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.quantity }} {{ entry.unit }}
                            </p>
                        </div>
                        <div
                            class="wm-surface-subtle rounded-[20px] p-4 lg:col-span-2"
                        >
                            <div class="flex items-start gap-3">
                                <FileText
                                    class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Catatan
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-6 text-slate-700 dark:text-slate-200"
                                    >
                                        {{ entry.note || '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    v-if="entry.can_approve || entry.can_reject || entry.rejection_note"
                    class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                >
                    <CardHeader><CardTitle>Approval Transaksi</CardTitle></CardHeader>
                    <CardContent class="space-y-4">
                        <p v-if="entry.rejection_note" class="text-sm text-slate-600 dark:text-slate-300">
                            Catatan penolakan: {{ entry.rejection_note }}
                        </p>
                        <div v-if="entry.can_approve" class="flex justify-end">
                            <Button @click="approve">Setujui Transaksi</Button>
                        </div>
                        <form v-if="entry.can_reject" class="space-y-3" @submit.prevent="reject">
                            <div class="grid gap-2">
                                <Label for="rejection_note">Catatan penolakan</Label>
                                <Textarea id="rejection_note" v-model="rejectForm.rejection_note" />
                                <InputError :message="rejectForm.errors.rejection_note" />
                            </div>
                            <div class="flex justify-end">
                                <Button variant="destructive" type="submit">Tolak Transaksi</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
