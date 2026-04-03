<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import WasteTransportationsController from '@/actions/App/Http/Controllers/WasteManagement/WasteTransportationsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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
import type { BreadcrumbItem } from '@/types';

interface Vendor {
    id: string;
    name: string;
}

interface WasteTransportation {
    id: string;
    transportation_number: string;
    waste_record_id: string;
    vendor_id: string;
    transportation_date: string;
    quantity: number;
    vehicle_number?: string | null;
    driver_name?: string | null;
    driver_phone?: string | null;
    notes?: string | null;
    waste_record?: {
        record_number: string;
        unit: string;
    };
}

type Props = {
    wasteTransportation: WasteTransportation;
    vendors: Vendor[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Transportasi Limbah',
        href: '/waste-management/transportations',
    },
    {
        title: props.wasteTransportation.transportation_number,
        href: `/waste-management/transportations/${props.wasteTransportation.id}`,
    },
    {
        title: 'Edit',
        href: `/waste-management/transportations/${props.wasteTransportation.id}/edit`,
    },
];

const formData = ref({
    waste_record_id: props.wasteTransportation.waste_record_id,
    vendor_id: props.wasteTransportation.vendor_id,
    transportation_date:
        props.wasteTransportation.transportation_date?.split('T')[0] ?? '',
    quantity: String(props.wasteTransportation.quantity),
    vehicle_number: props.wasteTransportation.vehicle_number ?? '',
    driver_name: props.wasteTransportation.driver_name ?? '',
    driver_phone: props.wasteTransportation.driver_phone ?? '',
    notes: props.wasteTransportation.notes ?? '',
});

function submit(): void {
    router.put(
        WasteTransportationsController.update(props.wasteTransportation.id),
        formData.value,
    );
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Edit Transportasi"
    >
        <Head :title="`Edit ${wasteTransportation.transportation_number}`" />

        <div
            class="relative max-w-3xl overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:max-w-5xl lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-blue-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <Heading
                    :title="`Edit ${wasteTransportation.transportation_number}`"
                    description="Perbarui detail transportasi agar vendor, jadwal, dan data pengiriman tetap akurat."
                />

                <form @submit.prevent="submit" class="space-y-6">
                    <div
                        class="overflow-hidden rounded-[30px] border border-slate-200 bg-white p-8 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] dark:bg-slate-950"
                    >
                        <div class="grid gap-2">
                            <Label>Catatan limbah</Label>
                            <Input
                                :model-value="
                                    wasteTransportation.waste_record
                                        ?.record_number || '-'
                                "
                                disabled
                            />
                            <Input
                                type="hidden"
                                v-model="formData.waste_record_id"
                                required
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="vendor_id">Vendor pengangkut *</Label>
                            <Select v-model="formData.vendor_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih vendor" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="vendor in vendors"
                                        :key="vendor.id"
                                        :value="vendor.id"
                                    >
                                        {{ vendor.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="transportation_date"
                                    >Tanggal transportasi *</Label
                                >
                                <Input
                                    id="transportation_date"
                                    type="date"
                                    v-model="formData.transportation_date"
                                    required
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="quantity">Jumlah angkut *</Label>
                                <Input
                                    id="quantity"
                                    type="number"
                                    v-model="formData.quantity"
                                    step="0.01"
                                    min="0.01"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="vehicle_number"
                                    >Nomor kendaraan</Label
                                >
                                <Input
                                    id="vehicle_number"
                                    v-model="formData.vehicle_number"
                                    placeholder="Contoh: B 1234 XYZ"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="driver_name">Nama pengemudi</Label>
                                <Input
                                    id="driver_name"
                                    v-model="formData.driver_name"
                                    placeholder="Nama pengemudi"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="driver_phone"
                                >Nomor telepon pengemudi</Label
                            >
                            <Input
                                id="driver_phone"
                                v-model="formData.driver_phone"
                                placeholder="Nomor telepon pengemudi"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes">Catatan transportasi</Label>
                            <Textarea
                                id="notes"
                                v-model="formData.notes"
                                rows="3"
                                placeholder="Tambahkan catatan atau instruksi penanganan"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit">Simpan perubahan</Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="
                                router.get(
                                    WasteTransportationsController.show(
                                        wasteTransportation.id,
                                    ),
                                )
                            "
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </WasteManagementLayout>
</template>
