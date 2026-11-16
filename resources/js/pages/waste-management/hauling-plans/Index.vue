<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    index,
    show,
    store,
} from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingPlansController';
import { index as haulingsIndex } from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
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
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { usePermissions } from '@/composables/usePermissions';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatLocalDate } from '@/lib/date';
import {
    availablePlanRecords,
    availablePlanWasteTypes,
    haulingPlanStatus,
    planRecordsForType,
    planRecordsForDateRange,
    planQuantityError,
} from '@/lib/hauling-plan';
import type { HaulingPlan, HaulingPlanRecord } from '@/types/hauling-plan';

const props = defineProps<{
    plansEnabled: boolean;
    plans: HaulingPlan[];
    records: HaulingPlanRecord[];
}>();
const page = usePage();
const { scopedUrl } = useOrganizationContext();
const { hasPermission } = usePermissions();
const selectableRecords = computed(() => availablePlanRecords(props.records));
const preselectedRecordId = new URL(
    page.url,
    'https://local.invalid',
).searchParams.get('waste_record');
const preselectedRecord = selectableRecords.value.find(
    (record) => record.id === preselectedRecordId,
);
const startDate = ref('');
const endDate = ref('');
const invalidDateRange = computed(
    () =>
        !!startDate.value && !!endDate.value && startDate.value > endDate.value,
);
const selectedWasteTypeId = ref(preselectedRecord?.waste_type_id ?? '');
const periodRecords = computed(() =>
    planRecordsForDateRange(
        selectableRecords.value,
        startDate.value,
        endDate.value,
    ),
);
const wasteTypes = computed(() => availablePlanWasteTypes(periodRecords.value));
const filteredRecords = computed(() =>
    planRecordsForType(periodRecords.value, selectedWasteTypeId.value),
);
type PlanItem = { waste_record_id: string; quantity: string | number };
const form = useForm({
    items: (preselectedRecord
        ? [{ waste_record_id: preselectedRecord.id, quantity: '' }]
        : []) as PlanItem[],
    planned_date: formatLocalDate(),
    notes: '',
});
function clearSelection(): void {
    form.items = [];
    form.clearErrors();
}
watch(
    [startDate, endDate],
    () => {
        selectedWasteTypeId.value = '';
        clearSelection();
    },
    { flush: 'sync' },
);
watch(selectedWasteTypeId, clearSelection, { flush: 'sync' });
const selectedItems = computed(() =>
    form.items.map((item) => {
        const record = filteredRecords.value.find(
            (record) => record.id === item.waste_record_id,
        );
        return {
            item,
            record,
            error: planQuantityError(item.quantity, record),
        };
    }),
);
const canSubmit = computed(
    () =>
        !form.processing &&
        !!form.planned_date &&
        !invalidDateRange.value &&
        form.items.length > 0 &&
        form.items.length <= 50 &&
        new Set(form.items.map((item) => item.waste_record_id)).size ===
            form.items.length &&
        selectedItems.value.every(({ error }) => !error),
);
function toggleRecord(recordId: string): void {
    if (form.processing) return;
    const existing = form.items.findIndex(
        (item) => item.waste_record_id === recordId,
    );
    if (existing >= 0) {
        form.items.splice(existing, 1);
    } else if (
        form.items.length < 50 &&
        filteredRecords.value.some((record) => record.id === recordId)
    ) {
        form.items.push({ waste_record_id: recordId, quantity: '' });
    }
    form.clearErrors();
}
function fieldError(
    index: number,
    field: 'quantity' | 'waste_record_id',
): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `items.${index}.${field}`
    ];
}
const flash = computed(
    () => page.props.flash as { success?: string; error?: string } | undefined,
);
const quantity = (value: number) =>
    value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
let submissionKey: string | undefined;
let submissionFingerprint: string | undefined;
function submit(): void {
    if (!canSubmit.value) return;
    const fingerprint = JSON.stringify({
        items: form.items,
        planned_date: form.planned_date,
        notes: form.notes,
    });
    if (!submissionKey || submissionFingerprint !== fingerprint) {
        submissionKey = crypto.randomUUID();
        submissionFingerprint = fingerprint;
    }
    form.post(scopedUrl(store.url()), {
        preserveScroll: true,
        headers: { 'Idempotency-Key': submissionKey },
        onError: () => {
            submissionKey = undefined;
            submissionFingerprint = undefined;
        },
        onSuccess: () => {
            if (!flash.value?.error) {
                submissionKey = undefined;
                submissionFingerprint = undefined;
                clearSelection();
            }
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
        <div class="min-w-0 space-y-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Rencana angkut" />
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
                dapat dilakukan sebagian. Rencana yang diajukan tampil di daftar
                rencana pada halaman ini, bukan sebagai pengangkutan aktual.
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
                            <Label for="plan-start">Limbah masuk sejak</Label>
                            <Input
                                id="plan-start"
                                v-model="startDate"
                                type="date"
                                :disabled="form.processing"
                                class="h-11"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="plan-end">Limbah masuk sampai</Label>
                            <Input
                                id="plan-end"
                                v-model="endDate"
                                type="date"
                                :disabled="form.processing"
                                class="h-11"
                                :min="startDate || undefined"
                            />
                        </div>
                        <p
                            v-if="invalidDateRange"
                            class="text-sm text-destructive md:col-span-2"
                            role="alert"
                        >
                            Tanggal akhir tidak boleh sebelum tanggal awal.
                        </p>
                        <p
                            v-else
                            class="text-sm text-muted-foreground md:col-span-2"
                        >
                            Rentang tanggal menyaring catatan masuk yang masih
                            tersedia. Kosongkan untuk melihat semua tanggal.
                        </p>
                        <div class="space-y-2">
                            <Label for="plan-waste-type">Jenis limbah</Label>
                            <select
                                id="plan-waste-type"
                                v-model="selectedWasteTypeId"
                                :disabled="
                                    form.processing || !wasteTypes.length
                                "
                                required
                                class="h-11 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="" disabled>
                                    Pilih jenis limbah
                                </option>
                                <option
                                    v-for="type in wasteTypes"
                                    :key="type.id"
                                    :value="type.id"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                            <p class="text-sm text-muted-foreground">
                                Pilih jenis limbah, lalu batch catatan yang
                                sudah disetujui dan masih tersedia untuk
                                dialokasikan.
                            </p>
                            <p
                                v-if="!hasPermission('waste_hauling.view_all')"
                                class="text-sm text-muted-foreground"
                            >
                                Pilihan pengajuan baru hanya berasal dari
                                catatan yang Anda buat. Untuk batch pengguna
                                lain, buka rencana koordinator yang sudah
                                disetujui supervisor pada Daftar rencana untuk
                                mencatat realisasi.
                            </p>
                            <p
                                v-if="
                                    !invalidDateRange && !periodRecords.length
                                "
                                role="status"
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    selectableRecords.length
                                        ? 'Tidak ada batch tersedia pada rentang tanggal ini. Kosongkan atau ubah rentang tanggal.'
                                        : 'Belum ada batch yang dapat Anda ajukan: catatan harus disetujui dan masih memiliki saldo untuk alokasi baru.'
                                }}
                            </p>
                        </div>
                        <fieldset
                            class="space-y-2 md:col-span-2"
                            :disabled="form.processing"
                        >
                            <legend class="text-sm font-medium">
                                Batch catatan limbah
                            </legend>
                            <p class="text-sm text-muted-foreground">
                                Pilih satu atau beberapa batch dari jenis limbah
                                yang sama (maksimal 50). Isi jumlah setiap
                                batch; tanggal dan catatan berlaku untuk semua
                                batch terpilih. Persetujuan dan realisasi tetap
                                per batch.
                            </p>
                            <p
                                v-if="!selectedWasteTypeId"
                                class="text-sm text-muted-foreground"
                            >
                                Pilih jenis limbah terlebih dahulu.
                            </p>
                            <div
                                v-else
                                class="max-h-64 space-y-2 overflow-y-auto rounded-md border p-3"
                            >
                                <label
                                    v-for="record in filteredRecords"
                                    :key="record.id"
                                    class="flex cursor-pointer items-start gap-3 rounded p-2 hover:bg-muted/50"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-1 size-4 shrink-0"
                                        :checked="
                                            form.items.some(
                                                (item) =>
                                                    item.waste_record_id ===
                                                    record.id,
                                            )
                                        "
                                        :disabled="
                                            form.processing ||
                                            (form.items.length >= 50 &&
                                                !form.items.some(
                                                    (item) =>
                                                        item.waste_record_id ===
                                                        record.id,
                                                ))
                                        "
                                        @change="toggleRecord(record.id)"
                                    />
                                    <span class="text-sm"
                                        >{{ record.record_number }} &bull; masuk
                                        {{ record.date || '-' }} &bull; tersedia
                                        {{
                                            quantity(record.available_quantity)
                                        }}
                                        {{ record.unit }}</span
                                    >
                                </label>
                            </div>
                            <InputError :message="form.errors.items" />
                            <p
                                class="text-sm text-muted-foreground"
                                role="status"
                            >
                                {{ form.items.length }} batch dipilih.
                            </p>
                        </fieldset>
                        <div
                            v-for="(
                                { item, record, error }, itemIndex
                            ) in selectedItems"
                            :key="item.waste_record_id"
                            class="space-y-2 rounded-md border p-3 md:col-span-2"
                        >
                            <Label
                                :for="`plan-quantity-${item.waste_record_id}`"
                                >Jumlah rencana
                                {{
                                    record?.record_number ||
                                    'batch tidak tersedia'
                                }}
                                {{ record ? `(${record.unit})` : '' }}</Label
                            >
                            <Input
                                :id="`plan-quantity-${item.waste_record_id}`"
                                v-model="item.quantity"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :max="record?.available_quantity"
                                :disabled="form.processing"
                                required
                                class="h-11"
                                :aria-invalid="!!error && item.quantity !== ''"
                                :aria-describedby="`plan-error-${item.waste_record_id}`"
                            />
                            <InputError
                                :message="
                                    fieldError(itemIndex, 'waste_record_id')
                                "
                            />
                            <InputError
                                :id="`plan-error-${item.waste_record_id}`"
                                role="alert"
                                :message="
                                    fieldError(itemIndex, 'quantity') ||
                                    (item.quantity !== '' ? error : '')
                                "
                            />
                            <p
                                v-if="record"
                                class="text-sm text-muted-foreground"
                            >
                                Saldo tercatat
                                {{ quantity(record.remaining_quantity) }}
                                {{ record.unit }}; tersedia untuk alokasi baru
                                {{ quantity(record.available_quantity) }}
                                {{ record.unit }}. Jumlah diisi sesuai rencana
                                muatan yang diperiksa. Satuan tidak dikonversi
                                otomatis.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="form.processing"
                                @click="toggleRecord(item.waste_record_id)"
                                >Hapus pilihan
                                {{ record?.record_number }}</Button
                            >
                        </div>
                        <div class="space-y-2">
                            <Label for="planned-date"
                                >Tanggal rencana angkut</Label
                            ><Input
                                id="planned-date"
                                v-model="form.planned_date"
                                :disabled="form.processing"
                                type="date"
                                required
                                class="h-11"
                            /><InputError :message="form.errors.planned_date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="plan-notes">Catatan rencana</Label
                            ><Textarea
                                id="plan-notes"
                                v-model="form.notes"
                                :disabled="form.processing"
                                rows="3"
                            /><InputError :message="form.errors.notes" />
                        </div>
                        <div class="md:col-span-2">
                            <Button type="submit" :disabled="!canSubmit">{{
                                form.processing
                                    ? 'Mengajukan...'
                                    : 'Ajukan rencana'
                            }}</Button>
                            <p
                                v-if="!selectableRecords.length"
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                Belum ada catatan limbah disetujui dengan saldo
                                tersedia untuk alokasi baru.
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
