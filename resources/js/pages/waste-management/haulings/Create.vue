<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import WasteHaulingsController from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingsController';
import Heading from '@/components/Heading.vue';
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
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import type { BreadcrumbItem } from '@/types';

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
    waste_type: {
        name: string | null;
        code: string | null;
        category: { name: string | null } | null;
    };
}

const props = defineProps<{ record: RecordItem }>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Pengangkutan Limbah', href: WasteHaulingsController.index().url },
    {
        title: 'Ajukan Angkut',
        href: WasteHaulingsController.create({
            query: { waste_record: props.record.id },
        }).url,
    },
];

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
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Ajukan Pengangkutan"
    >
        <Head title="Ajukan Pengangkutan Limbah" />

        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <Heading
                title="Ajukan Pengangkutan"
                description="Operator mengajukan quantity yang akan diangkut. Supervisor akan menyetujui pengajuan ini."
            />

            <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Form pengajuan</CardTitle>
                        <CardDescription
                            >Isi tanggal, quantity, dan catatan operasional
                            seperlunya.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <Label for="hauling_date">Tanggal angkut</Label>
                            <Input
                                id="hauling_date"
                                v-model="form.hauling_date"
                                type="date"
                            />
                            <p
                                v-if="form.errors.hauling_date"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.hauling_date }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="quantity">Quantity diajukan</Label>
                            <Input
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                min="0.01"
                                step="0.01"
                            />
                            <p class="text-xs text-muted-foreground">
                                Maksimal
                                {{ formatNumber(record.remaining_quantity) }}
                                {{ record.unit }}
                            </p>
                            <p
                                v-if="form.errors.quantity"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.quantity }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="notes">Catatan</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                rows="5"
                            />
                            <p
                                v-if="form.errors.notes"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.notes }}
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <Button :disabled="form.processing" @click="submit"
                                >Ajukan Angkut</Button
                            >
                            <Button as-child variant="outline">
                                <Link
                                    :href="WasteHaulingsController.index().url"
                                    >Kembali</Link
                                >
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ringkasan limbah</CardTitle>
                        <CardDescription>{{
                            record.record_number
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <span class="text-muted-foreground">Jenis:</span>
                            {{ record.waste_type.name || '-' }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Kategori:</span>
                            {{ record.waste_type.category?.name || '-' }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground"
                                >Masa simpan:</span
                            >
                            <ExpiryBadge
                                :expiry-date="record.expiry_date"
                                size="sm"
                            />
                        </div>
                        <div>
                            <span class="text-muted-foreground">Total:</span>
                            {{ formatNumber(record.quantity) }}
                            {{ record.unit }}
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Sudah diangkut:</span
                            >
                            {{ formatNumber(record.approved_hauled_quantity) }}
                            {{ record.unit }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Sisa:</span>
                            {{ formatNumber(record.remaining_quantity) }}
                            {{ record.unit }}
                        </div>
                        <div
                            v-if="
                                record.reserved_quantity >
                                record.approved_hauled_quantity
                            "
                        >
                            <span class="text-muted-foreground"
                                >Sedang diajukan:</span
                            >
                            {{
                                formatNumber(
                                    record.reserved_quantity -
                                        record.approved_hauled_quantity,
                                )
                            }}
                            {{ record.unit }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Sumber:</span>
                            {{ record.source || '-' }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
