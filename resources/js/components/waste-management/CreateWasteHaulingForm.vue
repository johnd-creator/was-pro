<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { index as plansIndex } from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingPlansController';
import WasteHaulingsController from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import VendorLicenseNotice from '@/components/VendorLicenseNotice.vue';
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import HaulingMetadataFields from '@/components/waste-management/HaulingMetadataFields.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { formatLocalDate } from '@/lib/date';
import { actualQuantityLimit, initialActualDate } from '@/lib/hauling-plan';
import type { HaulingActualFormProps } from '@/types/hauling-plan';

const props = defineProps<HaulingActualFormProps>();

const emit = defineEmits<{
    success: [];
}>();

const page = usePage();
const flash = computed(
    () => page.props.flash as { error?: string } | undefined,
);
const form = useForm({
    waste_record_id: props.record.id,
    hauling_date: initialActualDate(
        formatLocalDate(),
        props.plansEnabled ? props.selectedPlanId : null,
        props.actualDateLimit,
    ),
    quantity: '' as string | number,
    waste_hauling_plan_id: props.plansEnabled
        ? (props.selectedPlanId ?? '')
        : '',
    notes: '',
    vendor_id: '',
    vehicle_number: '',
    driver_name: '',
    document_number: '',
    destination: '',
    receiver: '',
    attachment: null as File | null,
});

const eligiblePlans = computed(() =>
    (props.approvedPlans ?? []).filter(
        (plan) =>
            plan.waste_record_id === props.record.id &&
            plan.abilities.can_realize,
    ),
);
const selectedPlan = computed(() =>
    eligiblePlans.value.find((plan) => plan.id === form.waste_hauling_plan_id),
);
const maximumQuantity = computed(() =>
    actualQuantityLimit(props.record, selectedPlan.value),
);
watch(
    () => form.waste_hauling_plan_id,
    () => {
        form.quantity = '';
        form.hauling_date = initialActualDate(
            formatLocalDate(),
            form.waste_hauling_plan_id,
            props.actualDateLimit,
        );
    },
);
let submissionKey: string | undefined;

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function submit(): void {
    if (props.record.requires_plan && !selectedPlan.value) {
        form.setError(
            'waste_hauling_plan_id',
            'Pilih rencana koordinator yang sudah disetujui.',
        );
        return;
    }
    submissionKey ??= crypto.randomUUID();
    form.post(scopedUrl(WasteHaulingsController.store().url), {
        preserveScroll: true,
        headers: { 'Idempotency-Key': submissionKey },
        onSuccess: () => {
            if (!flash.value?.error) {
                submissionKey = undefined;
                emit('success');
            }
        },
    });
}
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <div class="space-y-7">
        <div
            v-if="plansEnabled"
            class="space-y-3 rounded-lg border bg-muted/40 p-4 text-sm"
        >
            <p>
                Belum diangkut? Buat rencana lebih dulu. Form realisasi di bawah
                hanya untuk limbah yang sudah keluar.
            </p>
            <Button as-child variant="outline"
                ><Link
                    :href="
                        plansIndex.url({ query: { waste_record: record.id } })
                    "
                    >Buat atau tinjau rencana angkut</Link
                ></Button
            >
            <div class="space-y-2">
                <Label for="actual-plan">Rencana yang direalisasikan</Label
                ><select
                    id="actual-plan"
                    v-model="form.waste_hauling_plan_id"
                    class="h-11 w-full rounded-md border bg-background px-3 text-sm"
                >
                    <option v-if="!record.requires_plan" value="">
                        Tanpa tautan rencana (alur lama)
                    </option>
                    <option v-else value="" disabled>
                        Pilih rencana koordinator yang disetujui
                    </option>
                    <option
                        v-for="plan in eligiblePlans"
                        :key="plan.id"
                        :value="plan.id"
                    >
                        {{ plan.planned_date }} · sisa alokasi
                        {{ formatNumber(plan.remaining_plan_quantity) }}
                        {{ plan.unit }}
                    </option></select
                ><InputError :message="form.errors.waste_hauling_plan_id" />
            </div>
            <p v-if="selectedPlan">
                Tanggal rencana: {{ selectedPlan.planned_date }}. Isi tanggal
                aktual dan jumlah yang benar-benar keluar di bawah.
            </p>
        </div>
        <p class="text-sm leading-6 text-muted-foreground">
            Persetujuan realisasi mengurangi saldo tercatat. Tanggal rencana dan
            persetujuan rencana tidak menggantikan pencatatan realisasi.
        </p>
        <p v-if="flash?.error" role="alert" class="text-sm text-destructive">
            {{ flash.error }}
        </p>
        <InputError :message="form.errors.waste_record_id" />
        <div
            class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]"
        >
            <!-- Form Card -->
            <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                <CardHeader class="px-6 pt-6 pb-4 sm:px-7">
                    <CardTitle class="text-lg">Form realisasi</CardTitle>
                    <CardDescription class="max-w-md text-sm leading-6">
                        Isi tanggal aktual, jumlah yang benar-benar keluar, dan
                        bukti operasional yang tersedia.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6 px-6 pb-6 sm:px-7 sm:pb-7">
                    <div class="space-y-3.5">
                        <Label for="hauling_date" class="text-sm font-medium"
                            >Tanggal aktual angkut</Label
                        >
                        <Input
                            id="hauling_date"
                            v-model="form.hauling_date"
                            :min="selectedPlan ? record.date : undefined"
                            :max="selectedPlan ? actualDateLimit : undefined"
                            type="date"
                            class="h-11 w-full"
                        />
                        <InputError :message="form.errors.hauling_date" />
                        <p
                            v-if="selectedPlan && actualDateLimit"
                            class="text-sm text-muted-foreground"
                        >
                            Tanggal aktual dari {{ record.date }} sampai
                            {{ actualDateLimit }} (acuan server:
                            {{ actualDateTimezone || 'tidak tersedia' }}).
                            Tanggal lokal perangkat dapat berbeda.
                        </p>
                    </div>

                    <div class="space-y-3.5">
                        <Label for="quantity" class="text-sm font-medium"
                            >Jumlah aktual</Label
                        >
                        <Input
                            id="quantity"
                            v-model="form.quantity"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :max="maximumQuantity"
                            class="h-11 w-full"
                        />
                        <p class="text-sm text-muted-foreground">
                            Maksimal
                            {{ formatNumber(maximumQuantity) }}
                            {{ record.unit }}
                        </p>
                        <InputError :message="form.errors.quantity" />
                    </div>

                    <div class="space-y-3.5">
                        <Label for="notes" class="text-sm font-medium"
                            >Catatan</Label
                        >
                        <Textarea
                            id="notes"
                            v-model="form.notes"
                            rows="6"
                            class="min-h-[132px] resize-y"
                            placeholder="Catatan tambahan untuk supervisor..."
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                    <VendorLicenseNotice
                        v-if="metadataAvailable"
                        :vendor="
                            vendors?.find(
                                (vendor) => vendor.id === form.vendor_id,
                            )
                        "
                        :reference-date="form.hauling_date"
                    />
                    <HaulingMetadataFields
                        v-if="metadataAvailable"
                        :values="form"
                        :errors="form.errors"
                        :vendors="vendors"
                        @update="Object.assign(form, $event)"
                    />
                </CardContent>
            </Card>

            <!-- Summary Card -->
            <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                <CardHeader class="px-6 pt-6 pb-4 sm:px-7">
                    <CardTitle class="text-lg">Ringkasan limbah</CardTitle>
                    <CardDescription class="font-mono text-sm">
                        {{ record.record_number }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5 px-6 pb-6 sm:px-7 sm:pb-7">
                    <div class="grid gap-4 text-sm sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <p class="text-xs text-muted-foreground">Jenis</p>
                            <p class="text-sm leading-6 font-medium">
                                {{ record.waste_type.name || '-' }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-xs text-muted-foreground">
                                Kategori
                            </p>
                            <p class="text-sm leading-6 font-medium">
                                {{ record.waste_type.category?.name || '-' }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between gap-3 rounded-lg bg-muted/50 px-4 py-3"
                    >
                        <span class="text-sm font-medium">Masa simpan</span>
                        <ExpiryBadge
                            :expiry-date="record.expiry_date"
                            size="sm"
                        />
                    </div>

                    <div
                        class="space-y-4 rounded-lg border border-dashed px-5 py-4"
                    >
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Total</span
                            >
                            <span class="text-base font-semibold"
                                >{{ formatNumber(record.quantity) }}
                                {{ record.unit }}</span
                            >
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Realisasi disetujui</span
                            >
                            <span class="text-base font-semibold"
                                >{{
                                    formatNumber(
                                        record.approved_hauled_quantity,
                                    )
                                }}
                                {{ record.unit }}</span
                            >
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Saldo tercatat</span
                            >
                            <span
                                class="text-base font-bold text-emerald-600 dark:text-emerald-400"
                                >{{ formatNumber(record.remaining_quantity) }}
                                {{ record.unit }}</span
                            >
                        </div>
                    </div>

                    <div
                        v-if="record.pending_actual_quantity !== undefined"
                        class="space-y-2 rounded-lg bg-muted/50 p-4 text-sm"
                    >
                        <p>
                            Realisasi menunggu persetujuan:
                            {{ formatNumber(record.pending_actual_quantity) }}
                            {{ record.unit }}
                        </p>
                        <p
                            v-if="
                                plansEnabled &&
                                record.plan_reserved_quantity !== undefined
                            "
                        >
                            Alokasi rencana aktif:
                            {{ formatNumber(record.plan_reserved_quantity) }}
                            {{ record.unit }}
                        </p>
                        <p v-if="record.available_quantity !== undefined">
                            Tersedia untuk alokasi baru:
                            {{ formatNumber(record.available_quantity) }}
                            {{ record.unit }}
                        </p>
                    </div>

                    <div
                        class="flex items-baseline justify-between gap-4 text-sm"
                    >
                        <span class="text-muted-foreground">Sumber</span>
                        <span class="text-right leading-6 font-medium">{{
                            record.source || '-'
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Actions -->
        <div
            class="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-end"
        >
            <p class="mr-auto max-w-xl text-sm leading-6 text-muted-foreground">
                Ajukan setelah limbah benar-benar keluar. Supervisor meninjau
                realisasi sebelum saldo tercatat berkurang.
            </p>
            <Button
                type="button"
                size="default"
                :disabled="
                    form.processing ||
                    !form.quantity ||
                    maximumQuantity <= 0 ||
                    (!!form.waste_hauling_plan_id && !selectedPlan) ||
                    (record.requires_plan && !selectedPlan)
                "
                @click="submit"
            >
                <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                <span v-if="form.processing">Mengajukan...</span>
                <span v-else>Ajukan realisasi</span>
            </Button>
        </div>
    </div>
</template>
