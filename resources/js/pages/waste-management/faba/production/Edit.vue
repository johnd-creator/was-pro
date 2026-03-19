<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
import type { FabaProductionEntry } from '@/types/faba';

const props = defineProps<{
    entry: FabaProductionEntry;
    materialOptions: string[];
    entryTypeOptionsByMaterial: Record<string, string[]>;
    defaultUnit: string;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Produksi FABA',
        href: wasteManagementRoutes.faba.production.index.url(),
    },
    {
        title: 'Edit',
        href: wasteManagementRoutes.faba.production.edit(props.entry.id).url,
    },
];

const form = useForm<{
    transaction_date: string;
    material_type: string;
    entry_type: string;
    quantity: string;
    unit: string;
    note: string;
}>({
    transaction_date: props.entry.transaction_date,
    material_type: props.entry.material_type,
    entry_type: props.entry.entry_type,
    quantity: String(props.entry.quantity),
    unit: props.entry.unit,
    note: props.entry.note ?? '',
});

function submit(): void {
    form.put(wasteManagementRoutes.faba.production.update(props.entry.id).url);
}

const entryTypeOptions = computed(() =>
    props.entryTypeOptionsByMaterial[form.material_type] ?? [],
);

watch(
    () => form.material_type,
    () => {
        if (!entryTypeOptions.value.includes(form.entry_type)) {
            form.entry_type = '';
        }
    },
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Edit Produksi FABA"
    >
        <Head title="Edit Produksi FABA" />
        <div class="mx-auto max-w-3xl space-y-6">
            <Heading
                title="Edit Produksi FABA"
                description="Perbarui transaksi produksi."
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
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in materialOptions"
                                            :key="item"
                                            :value="item"
                                        >
                                            {{ item }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label>Tipe entri</Label>
                                <Select v-model="form.entry_type">
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in entryTypeOptions"
                                            :key="item"
                                            :value="item"
                                        >
                                            {{ item }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
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
                            <div class="grid gap-2">
                                <Label for="unit">Satuan</Label>
                                <Input id="unit" v-model="form.unit" readonly />
                                <p class="text-sm text-muted-foreground">
                                    Satuan transaksi ditetapkan ton.
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="note">Catatan</Label>
                            <Textarea id="note" v-model="form.note" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing"
                                >Simpan perubahan</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </WasteManagementLayout>
</template>
