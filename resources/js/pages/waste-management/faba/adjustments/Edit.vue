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
    entry: {
        id: string;
        display_number: string;
        transaction_date: string;
        material_type: string;
        movement_type: string;
        quantity: number;
        unit: string;
        note: string | null;
    };
    materialOptions: string[];
    movementTypeOptions: string[];
    defaultUnit: string;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Adjustment FABA', href: wasteManagementRoutes.faba.adjustments.index.url() },
    { title: 'Edit', href: wasteManagementRoutes.faba.adjustments.edit(props.entry.id).url },
];

const form = useForm({
    transaction_date: props.entry.transaction_date,
    material_type: props.entry.material_type,
    movement_type: props.entry.movement_type,
    quantity: String(props.entry.quantity),
    unit: props.entry.unit,
    note: props.entry.note ?? '',
});

function submit(): void {
    form.put(wasteManagementRoutes.faba.adjustments.update(props.entry.id).url);
}
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Edit Adjustment FABA">
        <Head title="Edit Adjustment FABA" />
        <div class="mx-auto max-w-3xl space-y-6">
            <Heading title="Edit Adjustment FABA" description="Perbarui koreksi stok FABA." />
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
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="item in materialOptions" :key="item" :value="item">{{ formatFabaMaterial(item) }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label>Tipe adjustment</Label>
                                <Select v-model="form.movement_type">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="item in movementTypeOptions" :key="item" :value="item">{{ formatFabaMovementType(item) }}</SelectItem>
                                    </SelectContent>
                                </Select>
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
                            <Button type="submit" :disabled="form.processing">Simpan perubahan</Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </WasteManagementLayout>
</template>
