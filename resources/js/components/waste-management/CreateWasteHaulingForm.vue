<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import WasteHaulingsController from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
import InputError from '@/components/InputError.vue';
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
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';

interface WasteType {
    name: string | null;
    code: string | null;
    category: { name: string | null } | null;
}

interface RecordItem {
    id: string;
    record_number: string;
    date: string;
    expiry_date: string | null;
    quantity: number;
    unit: string;
    source: string | null;
    approved_hauled_quantity: number;
    remaining_quantity: number;
    reserved_quantity: number;
    waste_type: WasteType;
}

interface Props {
    record: RecordItem;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    success: [];
}>();

const form = useForm({
    waste_record_id: props.record.id,
    hauling_date: new Date().toISOString().slice(0, 10),
    quantity: props.record.remaining_quantity,
    notes: '',
});

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function submit(): void {
    form.post(WasteHaulingsController.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            emit('success');
        },
    });
}
</script>

<template>
    <div class="space-y-7">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]">
            <!-- Form Card -->
            <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                <CardHeader class="px-6 pt-6 pb-4 sm:px-7">
                    <CardTitle class="text-lg">Form pengajuan</CardTitle>
                    <CardDescription class="max-w-md text-sm leading-6">
                        Isi tanggal, quantity, dan catatan operasional.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6 px-6 pb-6 sm:px-7 sm:pb-7">
                    <div class="space-y-3.5">
                        <Label for="hauling_date" class="text-sm font-medium"
                            >Tanggal angkut</Label
                        >
                        <Input
                            id="hauling_date"
                            v-model="form.hauling_date"
                            type="date"
                            class="h-11 w-full"
                        />
                        <InputError :message="form.errors.hauling_date" />
                    </div>

                    <div class="space-y-3.5">
                        <Label for="quantity" class="text-sm font-medium"
                            >Quantity diajukan</Label
                        >
                        <Input
                            id="quantity"
                            v-model="form.quantity"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="h-11 w-full"
                        />
                        <p class="text-sm text-muted-foreground">
                            Maksimal
                            {{ formatNumber(record.remaining_quantity) }}
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

                    <div class="space-y-4 rounded-lg border border-dashed px-5 py-4">
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
                                >Sudah diangkut</span
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
                                >Sisa</span
                            >
                            <span
                                class="text-base font-bold text-emerald-600 dark:text-emerald-400"
                                >{{ formatNumber(record.remaining_quantity) }}
                                {{ record.unit }}</span
                            >
                        </div>
                    </div>

                    <div
                        v-if="
                            record.reserved_quantity >
                            record.approved_hauled_quantity
                        "
                        class="rounded-lg bg-amber-50 px-4 py-3 text-sm dark:bg-amber-950/30"
                    >
                        <div class="flex items-baseline justify-between">
                            <span class="text-amber-700 dark:text-amber-300"
                                >Sedang diajukan</span
                            >
                            <span
                                class="font-semibold text-amber-900 dark:text-amber-100"
                            >
                                {{
                                    formatNumber(
                                        record.reserved_quantity -
                                            record.approved_hauled_quantity,
                                    )
                                }}
                                {{ record.unit }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between gap-4 text-sm">
                        <span class="text-muted-foreground">Sumber</span>
                        <span class="text-right leading-6 font-medium">{{
                            record.source || '-'
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-end">
            <p class="mr-auto max-w-xl text-sm leading-6 text-muted-foreground">
                Supervisor akan menyetujui pengajuan ini sebelum proses angkut
                dilakukan.
            </p>
            <Button
                type="button"
                size="default"
                :disabled="form.processing"
                @click="submit"
            >
                <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                <span v-if="form.processing">Mengajukan...</span>
                <span v-else>Ajukan Angkut</span>
            </Button>
        </div>
    </div>
</template>
