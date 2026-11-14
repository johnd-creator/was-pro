<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarClock, FileText, ShieldCheck, Wrench } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    entry: {
        id: string;
        display_number: string;
        transaction_date: string;
        material_type: string;
        movement_type: string;
        quantity: number;
        unit: string;
        note: string | null;
        source_movement_id: string | null;
        source_captured_at: string | null;
        source_snapshot: {
            id: string;
            transaction_date: string;
            material_type: string;
            movement_type: string;
            quantity: number;
            unit: string;
            document_number: string | null;
        } | null;
        source_current: { quantity: number; unit: string } | null;
        period_label: string;
        approval_status: string;
        effective_status?: string;
        locked?: boolean;
        duplicate_warning?: {
            message: string;
        } | null;
        rejection_note?: string | null;
        can_approve?: boolean;
        can_reject?: boolean;
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Adjustment FABA',
        href: wasteManagementRoutes.faba.adjustments.index.url(),
    },
    {
        title: props.entry.display_number,
        href: wasteManagementRoutes.faba.adjustments.show(props.entry.id).url,
    },
];

const approveForm = useForm({ approval_note: '' });
const rejectForm = useForm({ rejection_note: '' });

function approve(): void {
    approveForm.post(
        scopedUrl(
            wasteManagementRoutes.faba.movements.approve(props.entry.id).url,
        ),
    );
}

function reject(): void {
    rejectForm.post(
        scopedUrl(
            wasteManagementRoutes.faba.movements.reject(props.entry.id).url,
        ),
    );
}
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Detail Adjustment FABA"
    >
        <Head title="Detail Adjustment FABA" />
        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="mx-auto max-w-5xl space-y-4">
                <section class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                        <div class="space-y-4">
                            <Heading :title="entry.display_number" />
                            <div class="flex flex-wrap gap-3">
                                <Badge
                                    variant="secondary"
                                    class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700 dark:text-slate-200"
                                    >{{
                                        formatFabaStatus(
                                            entry.effective_status ??
                                                entry.approval_status,
                                        )
                                    }}</Badge
                                >
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
                                    {{ entry.period_label }}
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3">
                            <div
                                class="rounded-[22px] border border-orange-200/80 bg-orange-50/90 px-4 py-4 shadow-sm shadow-orange-100/60"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-orange-700/80 uppercase"
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
                <Card v-if="entry.source_movement_id">
                    <CardHeader
                        ><CardTitle>Bukti transaksi asal</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-2">
                        <p>ID: {{ entry.source_movement_id }}</p>
                        <template v-if="entry.source_snapshot">
                            <p>
                                Bukti saat ditautkan ({{
                                    entry.source_captured_at
                                }}):
                                {{ entry.source_snapshot.transaction_date }} ·
                                {{
                                    formatFabaMovementType(
                                        entry.source_snapshot.movement_type,
                                    )
                                }}
                                · {{ entry.source_snapshot.quantity }}
                                {{ entry.source_snapshot.unit }}
                            </p>
                            <p v-if="entry.source_snapshot.document_number">
                                Dokumen:
                                {{ entry.source_snapshot.document_number }}
                            </p>
                        </template>
                        <p v-else>Bukti historis tautan belum tersedia.</p>
                        <p v-if="entry.source_current">
                            Nilai sumber saat ini:
                            {{ entry.source_current.quantity }}
                            {{ entry.source_current.unit }}
                        </p>
                        <p v-else>Transaksi asal tidak tersedia.</p>
                        <p>
                            Tautan tidak mengubah perhitungan stok atau membalik
                            transaksi asal secara otomatis.
                        </p>
                    </CardContent>
                </Card>

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
                                <Wrench
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
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Material
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ formatFabaMaterial(entry.material_type) }}
                            </p>
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
                                        Status
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaStatus(
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
                                Jumlah
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.quantity }} {{ entry.unit }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Periode
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.period_label }}
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
                                        Alasan koreksi
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
                    v-if="
                        entry.can_approve ||
                        entry.can_reject ||
                        entry.rejection_note
                    "
                    class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                >
                    <CardHeader
                        ><CardTitle>Approval Transaksi</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-4">
                        <p
                            v-if="entry.rejection_note"
                            class="text-sm text-slate-600 dark:text-slate-300"
                        >
                            Catatan penolakan: {{ entry.rejection_note }}
                        </p>
                        <div v-if="entry.can_approve" class="flex justify-end">
                            <Button @click="approve">Setujui Transaksi</Button>
                        </div>
                        <form
                            v-if="entry.can_reject"
                            class="space-y-3"
                            @submit.prevent="reject"
                        >
                            <div class="grid gap-2">
                                <Label for="rejection_note"
                                    >Catatan penolakan</Label
                                >
                                <Textarea
                                    id="rejection_note"
                                    v-model="rejectForm.rejection_note"
                                />
                                <InputError
                                    :message="rejectForm.errors.rejection_note"
                                />
                            </div>
                            <div class="flex justify-end">
                                <Button variant="destructive" type="submit"
                                    >Tolak Transaksi</Button
                                >
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
