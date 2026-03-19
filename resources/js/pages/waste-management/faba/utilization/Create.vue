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
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaVendor } from '@/types/faba';

const props = defineProps<{
    vendors: FabaVendor[];
    materialOptions: string[];
    utilizationTypeOptions: string[];
    defaultUnit: string;
    requirements: Record<string, { requiresVendor: boolean; requiresDocument: boolean }>;
}>();

const NO_VENDOR = '__none__';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pemanfaatan FABA',
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
    utilization_type: '',
    vendor_id: NO_VENDOR,
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
            data.utilization_type === 'internal' || data.vendor_id === NO_VENDOR
                ? null
                : data.vendor_id,
    })).post(wasteManagementRoutes.faba.utilization.store.url());
}

function updateAttachment(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

watch(
    () => form.utilization_type,
    (value) => {
        if (value === 'internal') {
            form.vendor_id = NO_VENDOR;
            form.document_number = '';
            form.document_date = '';
        }
    },
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Tambah Pemanfaatan FABA"
    >
        <Head title="Tambah Pemanfaatan FABA" />

        <div class="mx-auto max-w-3xl space-y-6">
            <Heading
                title="Tambah Pemanfaatan FABA"
                description="Catat transaksi pemanfaatan baru."
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
                                <Select v-model="form.utilization_type">
                                    <SelectTrigger
                                        ><SelectValue placeholder="Pilih tipe"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in utilizationTypeOptions"
                                            :key="item"
                                            :value="item"
                                            >{{ item }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.utilization_type"
                                />
                            </div>
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>Vendor</Label>
                                <Select v-model="form.vendor_id">
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
                                <InputError :message="form.errors.vendor_id" />
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        props.requirements[form.utilization_type]?.requiresVendor
                                            ? 'Vendor wajib untuk pemanfaatan eksternal.'
                                            : 'Vendor boleh dikosongkan untuk pemanfaatan internal.'
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
                                    v-if="props.requirements[form.utilization_type]?.requiresDocument"
                                    class="text-sm text-muted-foreground"
                                >
                                    Nomor dokumen wajib untuk transaksi eksternal.
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
                                    v-if="props.requirements[form.utilization_type]?.requiresDocument"
                                    class="text-sm text-muted-foreground"
                                >
                                    Tanggal dokumen wajib untuk transaksi eksternal.
                                </p>
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
