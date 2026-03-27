<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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
import { formatFabaMaterial, formatFabaMovementType } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    materialOptions: string[];
    movementTypeOptions: string[];
    defaultUnit: string;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Adjustment FABA', href: wasteManagementRoutes.faba.adjustments.index.url() },
    { title: 'Tambah', href: wasteManagementRoutes.faba.adjustments.create.url() },
];

const form = useForm({
    transaction_date: new Date().toISOString().split('T')[0],
    material_type: '',
    movement_type: '',
    quantity: '',
    unit: props.defaultUnit,
    note: '',
});

function submit(): void {
    form.post(wasteManagementRoutes.faba.adjustments.store.url());
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Tambah Adjustment FABA">
        <Head title="Tambah Adjustment FABA" />
        <div class="mx-auto max-w-3xl space-y-6">
            <Heading title="Tambah Adjustment FABA" description="Catat koreksi stok FABA dengan alasan yang jelas." />
            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardContent class="space-y-6 p-6">
                        <div class="grid gap-2">
                            <Label for="transaction_date">Tanggal transaksi</Label>
                            <Input id="transaction_date" v-model="form.transaction_date" type="date" />
                            <InputError :message="form.errors.transaction_date" />
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>Material</Label>
                                <Select v-model="form.material_type">
                                    <SelectTrigger><SelectValue placeholder="Pilih material" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="item in materialOptions" :key="item" :value="item">{{ formatFabaMaterial(item) }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.material_type" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Tipe adjustment</Label>
                                <Select v-model="form.movement_type">
                                    <SelectTrigger><SelectValue placeholder="Pilih tipe adjustment" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="item in movementTypeOptions" :key="item" :value="item">{{ formatFabaMovementType(item) }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.movement_type" />
                            </div>
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="quantity">Jumlah</Label>
                                <Input id="quantity" v-model="form.quantity" type="number" step="0.01" />
                                <InputError :message="form.errors.quantity" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="unit">Satuan</Label>
                                <Input id="unit" v-model="form.unit" readonly />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="note">Alasan koreksi</Label>
                            <Textarea id="note" v-model="form.note" />
                            <InputError :message="form.errors.note" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">Simpan</Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </WasteManagementLayout>
</template>
