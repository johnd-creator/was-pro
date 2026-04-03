<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Clock, CheckCircle } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import WasteTransportationsController from '@/actions/App/Http/Controllers/WasteManagement/WasteTransportationsController';
import Heading from '@/components/Heading.vue';
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
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface Vendor {
    id: string;
    name: string;
}

interface WasteRecordOption {
    id: string;
    record_number: string;
    date: string;
    waste_type: string;
    category: string;
    total_quantity: number;
    unit: string;
    transported_quantity: number;
    remaining_quantity: number;
    is_expired: boolean;
    is_expiring_soon: boolean;
}

interface Prefill {
    waste_record_id: string;
    record_number: string;
    waste_type: string;
    category: string;
    unit: string;
    total_quantity: number;
    transported_quantity: number;
    remaining_quantity: number;
    is_expired: boolean;
    is_expiring_soon: boolean;
    expiry_date?: string;
}

type Props = {
    wasteRecords: WasteRecordOption[];
    vendors: Vendor[];
    prefill: Prefill | null;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Transportasi Limbah',
        href: '/waste-management/transportations',
    },
    {
        title: 'Buat Transportasi',
        href: '/waste-management/transportations/create',
    },
];

const formData = ref({
    waste_record_id: props.prefill?.waste_record_id || '',
    vendor_id: '',
    transportation_date: new Date().toISOString().split('T')[0],
    quantity: props.prefill?.remaining_quantity?.toString() || '',
    vehicle_number: '',
    driver_name: '',
    driver_phone: '',
    notes: '',
});

// Get selected waste record details
const selectedWasteRecord = computed(() => {
    if (!formData.value.waste_record_id) return null;
    return props.wasteRecords.find(
        (wr) => wr.id === formData.value.waste_record_id,
    );
});

// Warning for selected waste record
const wasteRecordWarning = computed(() => {
    const record = selectedWasteRecord.value;
    if (!record) return null;

    if (record.is_expired) {
        return {
            type: 'error',
            icon: AlertTriangle,
            title: 'Limbah Melewati Batas Simpan',
            message:
                'Limbah ini sudah melewati batas simpan dan perlu diprioritaskan untuk pengangkutan segera.',
            class: 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 text-red-800 dark:text-red-200',
        };
    }

    if (record.is_expiring_soon) {
        return {
            type: 'warning',
            icon: Clock,
            title: 'Segera Mencapai Batas Simpan',
            message:
                'Limbah ini akan segera mencapai batas simpan. Rencanakan pengangkutan secepatnya.',
            class: 'bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800 text-orange-800 dark:text-orange-200',
        };
    }

    return {
        type: 'success',
        icon: CheckCircle,
        title: 'Kondisi Masih Aman',
        message: 'Limbah ini masih berada dalam kondisi penyimpanan yang aman.',
        class: 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800 text-green-800 dark:text-green-200',
    };
});

// Watch for waste record selection to update quantity
watch(
    () => formData.value.waste_record_id,
    (newVal) => {
        if (newVal) {
            const record = props.wasteRecords.find((wr) => wr.id === newVal);
            if (record && !formData.value.quantity) {
                formData.value.quantity = record.remaining_quantity.toString();
            }
        }
    },
);

function submit() {
    router.post(WasteTransportationsController.store(), formData.value);
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Buat Transportasi Limbah"
    >
        <Head title="Buat Transportasi Limbah - Manajemen Limbah" />

        <div
            class="relative mx-auto w-full max-w-2xl overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 md:max-w-3xl lg:max-w-5xl lg:px-8 lg:py-8"
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
                    title="Buat Transportasi Limbah"
                    description="Susun pengangkutan limbah baru dengan vendor, jadwal, dan detail operasional yang jelas."
                />

                <!-- Pre-fill info if available -->
                <Card
                    v-if="prefill"
                    class="border border-blue-200 bg-blue-50 shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-blue-800 dark:bg-blue-900/20"
                >
                    <CardContent class="p-8">
                        <div class="flex items-start gap-3">
                            <CheckCircle
                                class="mt-0.5 h-5 w-5 text-blue-600 dark:text-blue-400"
                            />
                            <div class="flex-1">
                                <p
                                    class="text-sm font-medium text-blue-800 dark:text-blue-200"
                                >
                                    Membuat transportasi untuk:
                                    {{ prefill.record_number }}
                                </p>
                                <p
                                    class="mt-1 text-sm text-blue-700 dark:text-blue-300"
                                >
                                    {{ prefill.waste_type }} ({{
                                        prefill.category
                                    }})
                                </p>
                                <p
                                    class="mt-1 text-sm text-blue-700 dark:text-blue-300"
                                >
                                    Tersisa: {{ prefill.remaining_quantity }}
                                    {{ prefill.unit }} /
                                    {{ prefill.total_quantity }}
                                    {{ prefill.unit }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <form @submit.prevent="submit" class="space-y-8">
                    <!-- Waste Record Selection Card -->
                    <Card
                        class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] transition-shadow duration-300 hover:shadow-[0_28px_70px_-36px_rgba(15,23,42,0.38)] dark:bg-slate-950"
                    >
                        <CardContent class="space-y-6 p-8">
                            <div class="space-y-2">
                                <Label
                                    for="waste_record_id"
                                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >Catatan limbah *</Label
                                >
                                <Select
                                    v-model="formData.waste_record_id"
                                    required
                                >
                                    <SelectTrigger class="h-11">
                                        <SelectValue
                                            placeholder="Pilih catatan limbah"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="record in wasteRecords"
                                            :key="record.id"
                                            :value="record.id"
                                        >
                                            <div
                                                class="flex w-full items-center justify-between gap-2"
                                            >
                                                <div class="flex-1">
                                                    <div class="font-medium">
                                                        {{
                                                            record.record_number
                                                        }}
                                                    </div>
                                                    <div
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {{ record.waste_type }}
                                                        | Tersisa:
                                                        {{
                                                            record.remaining_quantity
                                                        }}
                                                        {{ record.unit }}
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <ExpiryBadge
                                                        :expiry-date="
                                                            record.is_expired
                                                                ? '2024-01-01'
                                                                : record.is_expiring_soon
                                                                  ? new Date(
                                                                        Date.now() +
                                                                            5 *
                                                                                24 *
                                                                                60 *
                                                                                60 *
                                                                                1000,
                                                                    )
                                                                        .toISOString()
                                                                        .split(
                                                                            'T',
                                                                        )[0]
                                                                  : new Date(
                                                                        Date.now() +
                                                                            30 *
                                                                                24 *
                                                                                60 *
                                                                                60 *
                                                                                1000,
                                                                    )
                                                                        .toISOString()
                                                                        .split(
                                                                            'T',
                                                                        )[0]
                                                        "
                                                        size="sm"
                                                    />
                                                </div>
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Warning for selected waste record -->
                            <div
                                v-if="wasteRecordWarning"
                                class="rounded-md border p-4"
                                :class="wasteRecordWarning.class"
                            >
                                <div class="flex items-start gap-3">
                                    <component
                                        :is="wasteRecordWarning.icon"
                                        class="mt-0.5 h-5 w-5"
                                    />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">
                                            {{ wasteRecordWarning.title }}
                                        </p>
                                        <p class="mt-1 text-sm">
                                            {{ wasteRecordWarning.message }}
                                        </p>
                                        <p
                                            v-if="selectedWasteRecord"
                                            class="mt-1 text-sm opacity-80"
                                        >
                                            Total:
                                            {{
                                                selectedWasteRecord.total_quantity
                                            }}
                                            {{ selectedWasteRecord.unit }} |
                                            Terangkut:
                                            {{
                                                selectedWasteRecord.transported_quantity
                                            }}
                                            {{ selectedWasteRecord.unit }} |
                                            Tersisa:
                                            {{
                                                selectedWasteRecord.remaining_quantity
                                            }}
                                            {{ selectedWasteRecord.unit }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] transition-shadow duration-300 hover:shadow-[0_28px_70px_-36px_rgba(15,23,42,0.38)] dark:bg-slate-950"
                    >
                        <CardContent class="space-y-6 p-8">
                            <div class="space-y-2">
                                <Label
                                    for="vendor_id"
                                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >Vendor pengangkut *</Label
                                >
                                <Select v-model="formData.vendor_id" required>
                                    <SelectTrigger class="h-11">
                                        <SelectValue
                                            placeholder="Pilih vendor"
                                        />
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
                        </CardContent>
                    </Card>

                    <Card
                        class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] transition-shadow duration-300 hover:shadow-[0_28px_70px_-36px_rgba(15,23,42,0.38)] dark:bg-slate-950"
                    >
                        <CardContent class="space-y-6 p-8">
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label
                                        for="transportation_date"
                                        class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                        >Tanggal transportasi *</Label
                                    >
                                    <Input
                                        id="transportation_date"
                                        type="date"
                                        v-model="formData.transportation_date"
                                        required
                                        class="h-11"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label
                                        for="quantity"
                                        class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                        >Jumlah angkut *</Label
                                    >
                                    <Input
                                        id="quantity"
                                        v-model.number="formData.quantity"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        placeholder="0.00"
                                        class="h-11"
                                    />
                                    <p
                                        v-if="selectedWasteRecord"
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Maksimum:
                                        {{
                                            selectedWasteRecord.remaining_quantity
                                        }}
                                        {{ selectedWasteRecord.unit }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] transition-shadow duration-300 hover:shadow-[0_28px_70px_-36px_rgba(15,23,42,0.38)] dark:bg-slate-950"
                    >
                        <CardContent class="space-y-6 p-8">
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label
                                        for="vehicle_number"
                                        class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                        >Nomor kendaraan</Label
                                    >
                                    <Input
                                        id="vehicle_number"
                                        v-model="formData.vehicle_number"
                                        placeholder="Contoh: B 1234 XYZ"
                                        class="h-11"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label
                                        for="driver_name"
                                        class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                        >Nama pengemudi</Label
                                    >
                                    <Input
                                        id="driver_name"
                                        v-model="formData.driver_name"
                                        placeholder="Nama pengemudi"
                                        class="h-11"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] transition-shadow duration-300 hover:shadow-[0_28px_70px_-36px_rgba(15,23,42,0.38)] dark:bg-slate-950"
                    >
                        <CardContent class="space-y-6 p-8">
                            <div class="space-y-2">
                                <Label
                                    for="driver_phone"
                                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >Nomor telepon pengemudi</Label
                                >
                                <Input
                                    id="driver_phone"
                                    v-model="formData.driver_phone"
                                    placeholder="Contoh: 08123456789"
                                    class="h-11"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="notes"
                                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >Catatan transportasi</Label
                                >
                                <Textarea
                                    id="notes"
                                    v-model="formData.notes"
                                    placeholder="Tambahkan catatan atau instruksi penanganan..."
                                    rows="3"
                                    class="min-h-[80px]"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)] transition-shadow duration-300 hover:shadow-[0_28px_70px_-36px_rgba(15,23,42,0.38)] dark:bg-slate-950"
                    >
                        <CardContent class="p-8">
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center"
                            >
                                <Button type="submit">Buat transportasi</Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    @click="
                                        router.get(
                                            wasteManagementRoutes.transportations.index(),
                                        )
                                    "
                                >
                                    Batal
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </div>
    </WasteManagementLayout>
</template>
