<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

const props = defineProps<{
    currentBalance: number;
    yearlyRecap: {
        year: number;
        totals: { total_production: number; total_utilization: number };
    };
    openingBalanceDefaults: {
        year: number;
        month: number;
    };
}>();

const form = useForm({
    year: props.openingBalanceDefaults.year,
    month: props.openingBalanceDefaults.month,
    material_type: 'fly_ash',
    quantity: '',
    note: '',
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Saldo TPS FABA',
        href: wasteManagementRoutes.faba.recaps.balance.url(),
    },
];

function submit(): void {
    form.post(wasteManagementRoutes.faba.recaps.openingBalance.store.url());
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Saldo TPS FABA"
    >
        <Head title="Saldo TPS FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Saldo TPS FABA"
                description="Ringkasan posisi saldo saat ini."
            />
            <div class="grid gap-4 md:grid-cols-3">
                <Card
                    ><CardHeader
                        ><CardTitle>Saldo Saat Ini</CardTitle></CardHeader
                    ><CardContent>{{ currentBalance }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Total Produksi Tahun Ini</CardTitle
                        ></CardHeader
                    ><CardContent>{{
                        yearlyRecap.totals.total_production
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Total Pemanfaatan Tahun Ini</CardTitle
                        ></CardHeader
                    ><CardContent>{{
                        yearlyRecap.totals.total_utilization
                    }}</CardContent></Card
                >
            </div>
            <Card>
                <CardHeader><CardTitle>Set Opening Balance</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Tahun</Label>
                        <Input v-model="form.year" type="number" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Bulan</Label>
                        <Input v-model="form.month" type="number" min="1" max="12" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Material</Label>
                        <Select v-model="form.material_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="fly_ash">fly_ash</SelectItem>
                                <SelectItem value="bottom_ash">bottom_ash</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Jumlah</Label>
                        <Input v-model="form.quantity" type="number" step="0.01" min="0" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label>Catatan</Label>
                        <Textarea v-model="form.note" />
                    </div>
                    <div class="md:col-span-2">
                        <Button @click="submit" :disabled="form.processing">Simpan opening balance</Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
