<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaMovementType } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaInternalDestination,
    FabaPurpose,
    FabaVendor,
} from '@/types/faba';

const props = defineProps<{
    vendors: FabaVendor[];
    internalDestinations: FabaInternalDestination[];
    purposes: FabaPurpose[];
    materialOptions: string[];
    movementTypeOptions: string[];
    initialMovementType: string;
    defaultUnit: string;
    requirements: Record<
        string,
        {
            requiresVendor: boolean;
            requiresDocument: boolean;
            requiresInternalDestination: boolean;
        }
    >;
}>();

const NO_VENDOR = '__none__';
const NO_INTERNAL_DESTINATION = '__none__';
const NO_PURPOSE = '__none__';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.initialMovementType === 'utilization_internal' ? 'Pemanfaatan Internal' : 'Pemanfaatan Eksternal',
        href: wasteManagementRoutes.faba.utilization.index.url(),
    },
    {
        title: 'Tambah',
        href: wasteManagementRoutes.faba.utilization.create.url(),
    },
];

const form = useForm({
    transaction_date: new Date().toISOString().split('T')[0],
    material_type: '',
    movement_type: props.initialMovementType,
    vendor_id: NO_VENDOR,
    internal_destination_id: NO_INTERNAL_DESTINATION,
    purpose_id: NO_PURPOSE,
    quantity: '',
    unit: props.defaultUnit,
    document_number: '',
    document_date: '',
    attachment: null as File | null,
    note: '',
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        vendor_id:
            data.movement_type === 'utilization_internal' || data.vendor_id === NO_VENDOR
                ? null
                : data.vendor_id,
        internal_destination_id:
            data.movement_type === 'utilization_external' || data.internal_destination_id === NO_INTERNAL_DESTINATION
                ? null
                : data.internal_destination_id,
        purpose_id: data.purpose_id === NO_PURPOSE ? null : data.purpose_id,
    })).post(wasteManagementRoutes.faba.utilization.store.url());
}

function updateAttachment(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

watch(
    () => form.movement_type,
    (value) => {
        if (value === 'utilization_internal') {
            form.vendor_id = NO_VENDOR;
            form.document_number = '';
            form.document_date = '';
        } else if (value === 'utilization_external') {
            form.internal_destination_id = NO_INTERNAL_DESTINATION;
        }
    },
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        :title="
            form.movement_type === 'utilization_internal'
                ? 'Tambah Pemanfaatan Internal'
                : 'Tambah Pemanfaatan Eksternal'
        "
    >
        <Head
            :title="
                form.movement_type === 'utilization_internal'
                    ? 'Tambah Pemanfaatan Internal'
                    : 'Tambah Pemanfaatan Eksternal'
            "
        />

        <div class="mx-auto max-w-3xl space-y-6">
            <Heading
                :title="
                    form.movement_type === 'utilization_internal'
                        ? 'Tambah Pemanfaatan Internal'
                        : 'Tambah Pemanfaatan Eksternal'
                "
                :description="
                    form.movement_type === 'utilization_internal'
                        ? 'Catat movement pemanfaatan untuk tujuan internal.'
                        : 'Catat movement pemanfaatan untuk vendor eksternal.'
                "
            />
            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardContent class="space-y-6 p-6">
                        <div class="grid gap-2">
                            <Label for="transaction_date"
                                >Tanggal transaksi</Label
                            >
                            <Input
                                id="transaction_date"
                                v-model="form.transaction_date"
                                type="date"
                            />
                            <InputError
                                :message="form.errors.transaction_date"
                            />
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>Material</Label>
                                <Select v-model="form.material_type">
                                    <SelectTrigger
                                        ><SelectValue
                                            placeholder="Pilih material"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in materialOptions"
                                            :key="item"
                                            :value="item"
                                            >{{ item }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.material_type"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Tipe pemanfaatan</Label>
                                <Select v-model="form.movement_type">
                                    <SelectTrigger
                                        ><SelectValue placeholder="Pilih tipe"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in movementTypeOptions"
                                            :key="item"
                                            :value="item"
                                            >{{ formatFabaMovementType(item) }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.movement_type"
                                />
                            </div>
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>
                                    {{
                                        form.movement_type === 'utilization_internal'
                                            ? 'Tujuan internal'
                                            : 'Vendor'
                                    }}
                                </Label>
                                <Select
                                    v-if="form.movement_type === 'utilization_internal'"
                                    v-model="form.internal_destination_id"
                                >
                                    <SelectTrigger
                                        ><SelectValue
                                            placeholder="Pilih tujuan internal"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NO_INTERNAL_DESTINATION"
                                            >Pilih tujuan internal</SelectItem
                                        >
                                        <SelectItem
                                            v-for="destination in internalDestinations"
                                            :key="destination.id"
                                            :value="destination.id"
                                            >{{ destination.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <Select v-else v-model="form.vendor_id">
                                    <SelectTrigger
                                        ><SelectValue
                                            placeholder="Pilih vendor bila eksternal"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NO_VENDOR"
                                            >Tanpa vendor</SelectItem
                                        >
                                        <SelectItem
                                            v-for="vendor in vendors"
                                            :key="vendor.id"
                                            :value="vendor.id"
                                            >{{ vendor.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="
                                        form.movement_type === 'utilization_internal'
                                            ? form.errors.internal_destination_id
                                            : form.errors.vendor_id
                                    "
                                />
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        props.requirements[form.movement_type]?.requiresVendor
                                            ? 'Vendor wajib untuk pemanfaatan eksternal.'
                                            : 'Tujuan internal wajib dipilih untuk pemanfaatan internal.'
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="quantity">Jumlah</Label>
                                <Input
                                    id="quantity"
                                    v-model="form.quantity"
                                    type="number"
                                    step="0.01"
                                />
                                <InputError :message="form.errors.quantity" />
                            </div>
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="unit">Satuan</Label>
                                <Input id="unit" v-model="form.unit" readonly />
                                <p class="text-sm text-muted-foreground">
                                    Satuan transaksi ditetapkan ton.
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="document_number"
                                    >Nomor dokumen</Label
                                >
                                <Input
                                    id="document_number"
                                    v-model="form.document_number"
                                />
                                <p
                                    v-if="props.requirements[form.movement_type]?.requiresDocument"
                                    class="text-sm text-muted-foreground"
                                >
                                    Nomor dokumen wajib untuk transaksi eksternal.
                                </p>
                                <p
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    Dokumen eksternal tidak diperlukan untuk pemanfaatan internal.
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="document_date"
                                    >Tanggal dokumen</Label
                                >
                                <Input
                                    id="document_date"
                                    v-model="form.document_date"
                                    type="date"
                                />
                                <p
                                    v-if="props.requirements[form.movement_type]?.requiresDocument"
                                    class="text-sm text-muted-foreground"
                                >
                                    Tanggal dokumen wajib untuk transaksi eksternal.
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label>Use-case / purpose</Label>
                                <Select v-model="form.purpose_id">
                                    <SelectTrigger
                                        ><SelectValue placeholder="Pilih use-case (opsional)"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NO_PURPOSE"
                                            >Tanpa use-case</SelectItem
                                        >
                                        <SelectItem
                                            v-for="purpose in purposes"
                                            :key="purpose.id"
                                            :value="purpose.id"
                                            >{{ purpose.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.purpose_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="attachment">Lampiran</Label>
                                <Input
                                    id="attachment"
                                    type="file"
                                    @change="updateAttachment"
                                />
                                <InputError :message="form.errors.attachment" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="note">Catatan</Label>
                            <Textarea id="note" v-model="form.note" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing"
                                >Simpan</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </WasteManagementLayout>
</template>
