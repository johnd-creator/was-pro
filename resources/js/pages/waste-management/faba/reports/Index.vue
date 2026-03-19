<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{ currentYear: number }>();
const form = reactive({
    year: props.currentYear,
    month: new Date().getMonth() + 1,
});

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Laporan FABA', href: wasteManagementRoutes.faba.reports.index.url() },
];
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Laporan FABA">
        <Head title="Laporan FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Laporan FABA"
                description="Ekspor laporan bulanan dan tahunan dalam format CSV."
            />
            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-4 rounded-lg border p-6">
                    <div class="grid gap-2">
                        <Label>Tahun</Label>
                        <Input v-model="form.year" type="number" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Bulan</Label>
                        <Input
                            v-model="form.month"
                            type="number"
                            min="1"
                            max="12"
                        />
                    </div>
                    <Button
                        @click="
                            router.get(
                                wasteManagementRoutes.faba.reports.monthly.csv({
                                    query: { year: form.year, month: form.month },
                                }),
                            )
                        "
                        >Export Rekap Bulanan</Button
                    >
                </div>
                <div class="space-y-4 rounded-lg border p-6">
                    <div class="grid gap-2">
                        <Label>Tahun</Label>
                        <Input v-model="form.year" type="number" />
                    </div>
                    <Button
                        @click="
                            router.get(
                                wasteManagementRoutes.faba.reports.yearly.csv({
                                    query: { year: form.year },
                                }),
                            )
                        "
                        >Export Rekap Tahunan</Button
                    >
                    <Button
                        variant="outline"
                        @click="
                            router.get(
                                wasteManagementRoutes.faba.production.export.csv(),
                            )
                        "
                        >Export Transaksi Produksi</Button
                    >
                    <Button
                        variant="outline"
                        @click="
                            router.get(
                                wasteManagementRoutes.faba.utilization.export.csv(),
                            )
                        "
                        >Export Transaksi Pemanfaatan</Button
                    >
                </div>
            </div>
        </div>
    </WasteManagementLayout>
</template>
