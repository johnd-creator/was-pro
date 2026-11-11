<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    index,
    show,
    store,
} from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingPlansController';
import { index as haulingsIndex } from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { usePermissions } from '@/composables/usePermissions';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatLocalDate } from '@/lib/date';
import { haulingPlanStatus } from '@/lib/hauling-plan';
import type { HaulingPlan, HaulingPlanRecord } from '@/types/hauling-plan';

const props = defineProps<{
    plansEnabled: boolean;
    plans: HaulingPlan[];
    records: HaulingPlanRecord[];
}>();
const page = usePage();
const { scopedUrl } = useOrganizationContext();
const { hasPermission } = usePermissions();
const preselectedRecord = new URL(
    page.url,
    'https://local.invalid',
).searchParams.get('waste_record');
const form = useForm({
    waste_record_id:
        props.records.find((record) => record.id === preselectedRecord)?.id ??
        '',
    planned_date: formatLocalDate(),
    quantity: '' as string | number,
    notes: '',
});
const selectedRecord = computed(() =>
    props.records.find((record) => record.id === form.waste_record_id),
);
const flash = computed(
    () => page.props.flash as { success?: string; error?: string } | undefined,
);
const quantity = (value: number) =>
    value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
let submissionKey: string | undefined;
function submit(): void {
    submissionKey ??= crypto.randomUUID();
    form.post(scopedUrl(store.url()), {
        preserveScroll: true,
        headers: { 'Idempotency-Key': submissionKey },
        onSuccess: () => {
            if (!flash.value?.error) submissionKey = undefined;
        },
    });
}
</script>

<template>
    <WasteManagementLayout
        title="Rencana Angkut"
        :breadcrumbs="[{ title: 'Rencana Angkut', href: index.url() }]"
    >
        <Head title="Rencana Angkut" />
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Rencana angkut"
                    description="Rencanakan alokasi, minta persetujuan supervisor, lalu catat realisasi setelah limbah keluar."
                />
                <Button
                    v-if="
                        hasPermission('waste_hauling.view_all') ||
                        hasPermission('waste_hauling.view_own')
                    "
                    as-child
                    variant="outline"
                    ><Link :href="haulingsIndex.url()"
                        >Pengangkutan limbah</Link
                    ></Button
                >
            </div>
            <p class="rounded-lg border bg-muted/40 p-4 text-sm leading-6">
                Persetujuan rencana hanya mencadangkan alokasi. Saldo tercatat
                berkurang setelah realisasi disetujui supervisor. Realisasi
                dapat dilakukan sebagian.
            </p>
            <p
                v-if="flash?.success"
                role="status"
                class="text-sm text-emerald-700 dark:text-emerald-300"
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
            <Card v-if="plansEnabled && hasPermission('waste_hauling.submit')">
                <CardHeader
                    ><CardTitle>Buat rencana angkut</CardTitle
                    ><CardDescription
                        >Tanggal dan jumlah di sini merupakan rencana, bukan
                        bukti realisasi.</CardDescription
                    ></CardHeader
                >
                <CardContent>
                    <form
                        class="grid gap-5 md:grid-cols-2"
                        @submit.prevent="submit"
                    >
                        <div class="space-y-2">
                            <Label for="plan-record">Catatan limbah</Label
                            ><select
                                id="plan-record"
                                v-model="form.waste_record_id"
                                required
                                class="h-11 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="" disabled>
                                    Pilih catatan limbah
                                </option>
                                <option
                                    v-for="record in records"
                                    :key="record.id"
                                    :value="record.id"
                                >
                                    {{ record.record_number }} · tersedia
                                    {{ quantity(record.available_quantity) }}
                                    {{ record.unit }}
                                </option></select
                            ><InputError
                                :message="form.errors.waste_record_id"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="planned-date"
                                >Tanggal rencana angkut</Label
                            ><Input
                                id="planned-date"
                                v-model="form.planned_date"
                                type="date"
                                required
                                class="h-11"
                            /><InputError :message="form.errors.planned_date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="plan-quantity"
                                >Jumlah rencana
                                {{
                                    selectedRecord
                                        ? `(${selectedRecord.unit})`
                                        : ''
                                }}</Label
                            ><Input
                                id="plan-quantity"
                                v-model="form.quantity"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :max="selectedRecord?.available_quantity"
                                required
                                class="h-11"
                            /><InputError :message="form.errors.quantity" />
                            <p
                                v-if="selectedRecord"
                                class="text-sm text-muted-foreground"
                            >
                                Saldo tercatat
                                {{
                                    quantity(selectedRecord.remaining_quantity)
                                }}
                                {{ selectedRecord.unit }}; tersedia untuk
                                alokasi baru
                                {{
                                    quantity(selectedRecord.available_quantity)
                                }}
                                {{ selectedRecord.unit }}.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="plan-notes">Catatan rencana</Label
                            ><Textarea
                                id="plan-notes"
                                v-model="form.notes"
                                rows="3"
                            /><InputError :message="form.errors.notes" />
                        </div>
                        <div class="md:col-span-2">
                            <Button
                                type="submit"
                                :disabled="
                                    form.processing ||
                                    !selectedRecord ||
                                    selectedRecord.available_quantity <= 0
                                "
                                >{{
                                    form.processing
                                        ? 'Mengajukan...'
                                        : 'Ajukan rencana'
                                }}</Button
                            >
                            <p
                                v-if="!records.length"
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                Belum ada catatan limbah disetujui yang dapat
                                direncanakan.
                            </p>
                        </div>
                    </form>
                </CardContent>
            </Card>
            <section class="space-y-4" aria-labelledby="plan-list-title">
                <h2 id="plan-list-title" class="text-lg font-semibold">
                    Daftar rencana
                </h2>
                <p
                    v-if="!plans.length"
                    class="rounded-lg border p-6 text-sm text-muted-foreground"
                >
                    Belum ada rencana angkut.
                </p>
                <div v-else class="grid gap-4 lg:grid-cols-2">
                    <Card v-for="plan in plans" :key="plan.id">
                        <CardHeader
                            ><CardTitle class="text-base"
                                ><Link
                                    :href="show.url(plan.id)"
                                    class="underline underline-offset-4"
                                    >{{ plan.record_number }} ·
                                    {{ plan.planned_date }}</Link
                                ></CardTitle
                            ><CardDescription>{{
                                haulingPlanStatus(plan.status)
                            }}</CardDescription></CardHeader
                        >
                        <CardContent class="space-y-2 text-sm"
                            ><p>
                                Rencana: {{ quantity(plan.quantity) }}
                                {{ plan.unit }}
                            </p>
                            <p>
                                Realisasi disetujui:
                                {{ quantity(plan.approved_actual_quantity) }}
                                {{ plan.unit }}
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
                            <p class="text-muted-foreground">
                                Pembuat: {{ plan.created_by?.name ?? '-' }}
                            </p>
                            <Button as-child variant="outline" class="mt-2"
                                ><Link :href="show.url(plan.id)"
                                    >Tinjau rencana</Link
                                ></Button
                            ></CardContent
                        >
                    </Card>
                </div>
            </section>
        </div>
    </WasteManagementLayout>
</template>
