<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Metadata {
    vendor_id: string;
    vehicle_number: string;
    driver_name: string;
    document_number: string;
    destination: string;
    receiver: string;
    attachment: File | null;
}

defineProps<{
    values: Metadata;
    errors: Partial<Record<keyof Metadata, string>>;
    vendors?: { id: string; name: string }[];
}>();

const emit = defineEmits<{ update: [values: Partial<Metadata>] }>();
const textFields = [
    { key: 'vehicle_number', label: 'Nomor kendaraan', max: 50 },
    { key: 'driver_name', label: 'Nama pengemudi', max: 255 },
    { key: 'document_number', label: 'Nomor manifest / dokumen', max: 255 },
    { key: 'destination', label: 'Tujuan', max: 255 },
    { key: 'receiver', label: 'Penerima', max: 255 },
] as const;
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            Metadata operasional (opsional). Tidak mengubah status persetujuan
            atau stok.
        </p>
        <div class="space-y-2">
            <Label for="vendor_id">Vendor pengangkut</Label>
            <select
                id="vendor_id"
                :value="values.vendor_id"
                class="w-full rounded-md border bg-background p-2"
                @change="
                    emit('update', {
                        vendor_id: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Tanpa vendor</option>
                <option
                    v-for="vendor in vendors"
                    :key="vendor.id"
                    :value="vendor.id"
                >
                    {{ vendor.name }}
                </option>
            </select>
            <InputError :message="errors.vendor_id" />
        </div>
        <div v-for="field in textFields" :key="field.key" class="space-y-2">
            <Label :for="field.key">{{ field.label }}</Label>
            <Input
                :id="field.key"
                :model-value="values[field.key]"
                :maxlength="field.max"
                @update:model-value="
                    emit('update', { [field.key]: String($event) })
                "
            />
            <InputError :message="errors[field.key]" />
        </div>
        <div class="space-y-2">
            <Label for="attachment"
                >Bukti (JPG, PNG, WEBP atau PDF, maksimal 5 MB)</Label
            >
            <input
                id="attachment"
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                @change="
                    emit('update', {
                        attachment:
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null,
                    })
                "
            />
            <InputError :message="errors.attachment" />
        </div>
    </div>
</template>
