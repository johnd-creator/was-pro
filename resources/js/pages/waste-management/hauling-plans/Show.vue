<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    approve,
    close,
    index,
    reject,
    show,
} from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingPlansController';
import {
    create as createActual,
    show as showActual,
} from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Link from '@/components/OrganizationLink.vue';
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
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { usePermissions } from '@/composables/usePermissions';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { haulingActualStatus, haulingPlanStatus } from '@/lib/hauling-plan';
import type { HaulingPlan, HaulingPlanRecord } from '@/types/hauling-plan';
const props = defineProps<{ plan: HaulingPlan; record: HaulingPlanRecord }>();
const page = usePage();
const { hasPermission } = usePermissions();
const flash = computed(
    () => page.props.flash as { success?: string; error?: string } | undefined,
);
const { scopedUrl } = useOrganizationContext();
const approveForm = useForm({ approval_notes: '' });
const rejectForm = useForm({ rejection_reason: '' });
const closeForm = useForm({ closure_reason: '' });
const busy = computed(
    () =>
        approveForm.processing || rejectForm.processing || closeForm.processing,
);
const quantity = (value: number) =>
    value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
function approvePlan(): void {
    approveForm.post(scopedUrl(approve.url(props.plan.id)), {
        preserveScroll: true,
    });
}
function rejectPlan(): void {
    rejectForm.post(scopedUrl(reject.url(props.plan.id)), {
        preserveScroll: true,
    });
}
function closePlan(): void {
    closeForm.post(scopedUrl(close.url(props.plan.id)), {
        preserveScroll: true,
    });
}
</script>
<template>
    <WasteManagementLayout
        title="Detail Rencana Angkut"
        :breadcrumbs="[
            { title: 'Rencana Angkut', href: index.url() },
            { title: plan.record_number, href: show.url(plan.id) },
        ]"
    >
        <Head title="Detail Rencana Angkut" />
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <Heading
                title="Detail rencana angkut"
                :description="`${plan.record_number} · ${haulingPlanStatus(plan.status)}`"
            />
            <p
                v-if="flash?.success"
                role="status"
                class="rounded-lg border p-4 text-sm"
            >
                {{ flash.success }}
            </p>
            <p
                v-if="flash?.error"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ flash.error }}
            </p>
            <p class="rounded-lg border bg-muted/40 p-4 text-sm leading-6">
                Rencana disetujui tidak mengurangi saldo tercatat. Catat jumlah
                dan tanggal aktual setelah limbah keluar; setiap realisasi
                memerlukan persetujuan supervisor.
            </p>
            <div class="grid gap-6 lg:grid-cols-2">
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Rencana dan persetujuan</CardTitle
                        ></CardHeader
                    ><CardContent class="space-y-3 text-sm"
                        ><p>Tanggal rencana: {{ plan.planned_date }}</p>
                        <p>
                            Jumlah rencana: {{ quantity(plan.quantity) }}
                            {{ plan.unit }}
                        </p>
                        <p>Pembuat: {{ plan.created_by?.name ?? '-' }}</p>
                        <p>Diajukan: {{ plan.submitted_at ?? '-' }}</p>
                        <p>
                            Penyetuju rencana:
                            {{ plan.approved_by?.name ?? '-' }}
                        </p>
                        <p>
                            Rencana disetujui pada:
                            {{ plan.approved_at ?? '-' }}
                        </p>
                        <p>Catatan: {{ plan.notes || '-' }}</p>
                        <p v-if="plan.approval_notes">
                            Catatan persetujuan: {{ plan.approval_notes }}
                        </p>
                        <p v-if="plan.rejection_reason">
                            Alasan penolakan: {{ plan.rejection_reason }}
                        </p>
                        <p v-if="plan.closure_reason">
                            Alasan penutupan: {{ plan.closure_reason }}
                        </p>
                        <p v-if="plan.closed_by">
                            Ditutup oleh: {{ plan.closed_by.name }}
                        </p>
                        <p v-if="plan.closed_at">
                            Ditutup pada: {{ plan.closed_at }}
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Alokasi dan realisasi</CardTitle
                        ><CardDescription
                            >Setiap nilai tetap menggunakan satuan catatan
                            limbah.</CardDescription
                        ></CardHeader
                    ><CardContent class="space-y-3 text-sm"
                        ><p>
                            Realisasi disetujui:
                            <strong
                                >{{ quantity(plan.approved_actual_quantity) }}
                                {{ plan.unit }}</strong
                            >
                        </p>
                        <p>
                            Realisasi menunggu persetujuan:
                            {{ quantity(plan.pending_actual_quantity) }}
                            {{ plan.unit }}
                        </p>
                        <p>
                            Sisa alokasi rencana:
                            {{ quantity(plan.remaining_plan_quantity) }}
                            {{ plan.unit }}
                        </p>
                        <p>
                            Saldo tercatat catatan limbah:
                            {{ quantity(record.remaining_quantity) }}
                            {{ record.unit }}
                        </p>
                        <p class="text-muted-foreground">
                            Sisa alokasi belum termasuk realisasi yang sedang
                            menunggu persetujuan. Rencana ditutup melepaskan
                            sisa alokasi tanpa menghapus realisasi sebelumnya.
                        </p>
                        <Button
                            v-if="
                                plan.abilities.can_realize &&
                                hasPermission('waste_hauling.create')
                            "
                            as-child
                            ><Link
                                :href="
                                    createActual.url({
                                        query: {
                                            waste_record: plan.waste_record_id,
                                            waste_hauling_plan_id: plan.id,
                                        },
                                    })
                                "
                                >Catat realisasi dari rencana</Link
                            ></Button
                        ></CardContent
                    ></Card
                >
            </div>
            <div class="grid gap-6 lg:grid-cols-2">
                <Card v-if="plan.abilities.can_approve"
                    ><CardHeader
                        ><CardTitle>Setujui rencana</CardTitle
                        ><CardDescription
                            >Hanya menyetujui alokasi, bukan realisasi atau
                            pengurangan saldo.</CardDescription
                        ></CardHeader
                    ><CardContent
                        ><form class="space-y-4" @submit.prevent="approvePlan">
                            <Label for="plan-approval-notes"
                                >Catatan persetujuan</Label
                            ><Textarea
                                id="plan-approval-notes"
                                v-model="approveForm.approval_notes"
                            /><InputError
                                v-for="(error, field) in approveForm.errors"
                                :key="field"
                                :message="error"
                            /><Button type="submit" :disabled="busy"
                                >Setujui rencana</Button
                            >
                        </form></CardContent
                    ></Card
                >
                <Card v-if="plan.abilities.can_reject"
                    ><CardHeader
                        ><CardTitle>Tolak rencana</CardTitle></CardHeader
                    ><CardContent
                        ><form class="space-y-4" @submit.prevent="rejectPlan">
                            <Label for="plan-rejection">Alasan penolakan</Label
                            ><Textarea
                                id="plan-rejection"
                                v-model="rejectForm.rejection_reason"
                                required
                            /><InputError
                                v-for="(error, field) in rejectForm.errors"
                                :key="field"
                                :message="error"
                            /><Button
                                type="submit"
                                variant="destructive"
                                :disabled="
                                    busy || !rejectForm.rejection_reason.trim()
                                "
                                >Tolak rencana</Button
                            >
                        </form></CardContent
                    ></Card
                >
                <Card v-if="plan.abilities.can_close"
                    ><CardHeader
                        ><CardTitle>Tutup sisa rencana</CardTitle
                        ><CardDescription
                            >Sisa alokasi dilepas. Realisasi yang telah
                            disetujui tetap tercatat.</CardDescription
                        ></CardHeader
                    ><CardContent
                        ><form class="space-y-4" @submit.prevent="closePlan">
                            <Label for="plan-closure"
                                >Alasan penutupan sisa</Label
                            ><Textarea
                                id="plan-closure"
                                v-model="closeForm.closure_reason"
                                required
                            /><InputError
                                v-for="(error, field) in closeForm.errors"
                                :key="field"
                                :message="error"
                            /><Button
                                type="submit"
                                variant="outline"
                                :disabled="
                                    busy || !closeForm.closure_reason.trim()
                                "
                                >Tutup sisa rencana</Button
                            >
                        </form></CardContent
                    ></Card
                >
            </div>
            <p
                v-if="
                    plan.status === 'approved' &&
                    plan.pending_actual_quantity > 0
                "
                class="text-sm text-muted-foreground"
            >
                Sisa rencana belum dapat ditutup selama ada realisasi menunggu
                persetujuan.
            </p>
            <section class="space-y-3" aria-labelledby="actual-list-title">
                <h2 id="actual-list-title" class="text-lg font-semibold">
                    Riwayat realisasi
                </h2>
                <p
                    v-if="!plan.actuals.length"
                    class="text-sm text-muted-foreground"
                >
                    Belum ada realisasi. Persetujuan rencana bukan bukti bahwa
                    limbah telah keluar.
                </p>
                <div
                    v-for="actual in plan.actuals"
                    :key="actual.id"
                    class="flex flex-wrap justify-between gap-3 rounded-lg border p-4 text-sm"
                >
                    <div>
                        <Link
                            v-if="actual.can_view"
                            :href="showActual.url(actual.id)"
                            class="font-medium underline underline-offset-4"
                            >{{ actual.hauling_number }}</Link
                        >
                        <p v-if="!actual.can_view" class="font-medium">
                            {{ actual.hauling_number }}
                        </p>
                        <p class="mt-1">
                            Tanggal aktual: {{ actual.hauling_date }} ·
                            {{ quantity(actual.quantity) }} {{ actual.unit }}
                        </p>
                    </div>
                    <p>{{ haulingActualStatus(actual.status) }}</p>
                </div>
            </section>
            <Button as-child variant="outline"
                ><Link :href="index.url()">Kembali ke rencana</Link></Button
            >
        </div>
    </WasteManagementLayout>
</template>
