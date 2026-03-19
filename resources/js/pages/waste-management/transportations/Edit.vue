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
        title: 'Waste Transportations',
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
        title="Edit Transportation"
    >
        <Head :title="`Edit ${wasteTransportation.transportation_number}`" />

        <div class="max-w-2xl space-y-6">
            <Heading
                :title="`Edit ${wasteTransportation.transportation_number}`"
                description="Update transportation details"
            />

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid gap-2">
                    <Label>Waste Record</Label>
                    <Input
                        :model-value="
                            wasteTransportation.waste_record?.record_number ||
                            '-'
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
                    <Label for="vendor_id">Transportation Vendor *</Label>
                    <Select v-model="formData.vendor_id" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select vendor" />
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
                            >Transportation Date *</Label
                        >
                        <Input
                            id="transportation_date"
                            type="date"
                            v-model="formData.transportation_date"
                            required
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="quantity">Quantity *</Label>
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
                        <Label for="vehicle_number">Vehicle Number</Label>
                        <Input
                            id="vehicle_number"
                            v-model="formData.vehicle_number"
                            placeholder="e.g., B 1234 XYZ"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="driver_name">Driver Name</Label>
                        <Input
                            id="driver_name"
                            v-model="formData.driver_name"
                            placeholder="Driver name"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="driver_phone">Driver Phone</Label>
                    <Input
                        id="driver_phone"
                        v-model="formData.driver_phone"
                        placeholder="Driver phone number"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        v-model="formData.notes"
                        rows="3"
                        placeholder="Special instructions or notes"
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit">Save Changes</Button>
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
                        Cancel
                    </Button>
                </div>
            </form>
        </div>
    </WasteManagementLayout>
</template>
