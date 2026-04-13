<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { AlertCircle, CalendarClock } from 'lucide-vue-next';
import { computed } from 'vue';
import WasteRecordsController from '@/actions/App/Http/Controllers/WasteManagement/WasteRecordsController';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

interface WasteType {
    id: string;
    name: string;
    code: string;
    storage_period_days: number;
    category?: {
        name: string;
    };
    characteristic?: {
        name: string;
        is_hazardous: boolean;
    };
}

interface Props {
    wasteTypes: WasteType[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    success: [];
}>();

const form = useForm({
    date: new Date().toISOString().split('T')[0],
    waste_type_id: '',
    quantity: '',
    unit: 'kg',
    source: '',
    description: '',
    notes: '',
});

const selectedWasteType = computed(() =>
    props.wasteTypes.find((type) => type.id === form.waste_type_id),
);

const calculatedExpiryDate = computed(() => {
    if (!form.waste_type_id || !form.date) {
        return null;
    }

    if (!selectedWasteType.value) {
        return null;
    }

    if (selectedWasteType.value.storage_period_days === 0) {
        return null;
    }

    const date = new Date(form.date);
    const expiryDate = new Date(date);
    expiryDate.setDate(
        date.getDate() + selectedWasteType.value.storage_period_days,
    );

    return expiryDate;
});

const expiryInfo = computed(() => {
    if (!selectedWasteType.value) {
        return null;
    }

    if (!calculatedExpiryDate.value) {
        return {
            variant: 'neutral' as const,
            title: 'Tidak ada batas masa simpan',
            summary:
                'Jenis limbah ini tidak memiliki batas masa simpan otomatis.',
            recommendation:
                'Tetap catat kondisi penyimpanan sesuai prosedur internal.',
            daysLabel: 'Tidak dibatasi',
        };
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const expiryDate = new Date(calculatedExpiryDate.value);
    expiryDate.setHours(0, 0, 0, 0);

    const daysUntilExpiry = Math.ceil(
        (expiryDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    if (daysUntilExpiry < 0) {
        return {
            variant: 'danger' as const,
            title: 'Melewati batas masa simpan',
            summary: `Catatan ini akan melewati batas masa simpan ${Math.abs(daysUntilExpiry)} hari.`,
            recommendation:
                'Segera koordinasikan pengangkutan atau tindak lanjut penanganan limbah.',
            daysLabel: `${Math.abs(daysUntilExpiry)} hari terlewat`,
        };
    }

    if (daysUntilExpiry <= 7) {
        return {
            variant: 'warning' as const,
            title: 'Mendekati batas masa simpan',
            summary: `Limbah ini akan mencapai batas masa simpan dalam ${daysUntilExpiry} hari.`,
            recommendation:
                'Rencanakan pengangkutan lebih awal agar tidak melewati batas penyimpanan.',
            daysLabel: `${daysUntilExpiry} hari tersisa`,
        };
    }

    return {
        variant: 'safe' as const,
        title: 'Masa simpan masih aman',
        summary: `Limbah ini masih memiliki masa simpan ${daysUntilExpiry} hari.`,
        recommendation:
            'Lanjutkan pencatatan dan pemantauan sesuai jadwal operasional.',
        daysLabel: `${daysUntilExpiry} hari tersisa`,
    };
});

function submit(): void {
    form.post(WasteRecordsController.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            emit('success');
        },
    });
}

function getHazardousClass(isHazardous: boolean): string {
    return isHazardous
        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
}
</script>

<template>
    <div class="space-y-4">
        <Alert v-if="form.hasErrors" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Catatan belum dapat disimpan</AlertTitle>
            <AlertDescription>
                Periksa kembali field yang ditandai lalu coba lagi.
            </AlertDescription>
        </Alert>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Basic Information Card -->
            <Card class="border-slate-200 dark:border-slate-800">
                <CardContent class="space-y-4 p-6">
                    <div class="grid gap-3">
                        <Label for="date" class="text-sm"
                            >Tanggal pencatatan *</Label
                        >
                        <Input
                            id="date"
                            v-model="form.date"
                            type="date"
                            required
                            class="w-full"
                        />
                        <p class="text-xs text-muted-foreground">
                            Gunakan tanggal saat limbah dicatat atau diterima.
                        </p>
                        <InputError :message="form.errors.date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="waste_type_id" class="text-sm"
                            >Jenis limbah *</Label
                        >
                        <Select v-model="form.waste_type_id" required>
                            <SelectTrigger id="waste_type_id">
                                <SelectValue placeholder="Pilih jenis limbah" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in props.wasteTypes"
                                    :key="type.id"
                                    :value="type.id"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            >{{ type.name }} ({{
                                                type.code
                                            }})</span
                                        >
                                        <span
                                            v-if="type.characteristic"
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                getHazardousClass(
                                                    type.characteristic
                                                        .is_hazardous,
                                                )
                                            "
                                        >
                                            {{ type.category?.name }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Pilih jenis limbah yang paling sesuai agar kategori
                            dan masa simpan dihitung dengan benar.
                        </p>
                        <InputError :message="form.errors.waste_type_id" />
                    </div>
                </CardContent>
            </Card>

            <!-- Expiry Information Card -->
            <Card
                v-if="expiryInfo"
                class="border-slate-200 dark:border-slate-800"
                :class="{
                    'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20':
                        expiryInfo.variant === 'danger',
                    'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20':
                        expiryInfo.variant === 'warning',
                    'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20':
                        expiryInfo.variant === 'safe',
                    'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/20':
                        expiryInfo.variant === 'neutral',
                }"
            >
                <CardContent class="space-y-4 p-6">
                    <div class="flex items-start gap-3">
                        <component
                            :is="
                                expiryInfo.variant === 'danger' ||
                                expiryInfo.variant === 'warning'
                                    ? AlertCircle
                                    : CalendarClock
                            "
                            class="mt-0.5 h-5 w-5 shrink-0"
                            :class="{
                                'text-red-600 dark:text-red-400':
                                    expiryInfo.variant === 'danger',
                                'text-amber-600 dark:text-amber-400':
                                    expiryInfo.variant === 'warning',
                                'text-green-600 dark:text-green-400':
                                    expiryInfo.variant === 'safe',
                                'text-slate-600 dark:text-slate-300':
                                    expiryInfo.variant === 'neutral',
                            }"
                        />
                        <div class="flex-1 space-y-2">
                            <div>
                                <p
                                    class="text-base font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{ expiryInfo.title }}
                                </p>
                                <p
                                    class="mt-1 text-sm text-slate-700 dark:text-slate-200"
                                >
                                    {{ expiryInfo.summary }}
                                </p>
                            </div>

                            <div
                                class="grid gap-3 rounded-md bg-background/70 p-3 text-sm md:grid-cols-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        Tanggal kedaluwarsa
                                    </p>
                                    <p
                                        class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            calculatedExpiryDate
                                                ? format(
                                                      calculatedExpiryDate,
                                                      'PPP',
                                                  )
                                                : '-'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        Sisa masa simpan
                                    </p>
                                    <p
                                        class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{ expiryInfo.daysLabel }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        Rekomendasi
                                    </p>
                                    <p
                                        class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{ expiryInfo.recommendation }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quantity & Unit Card -->
            <Card class="border-slate-200 dark:border-slate-800">
                <CardContent class="space-y-4 p-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="quantity" class="text-sm"
                                >Jumlah *</Label
                            >
                            <Input
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                placeholder="0.00"
                            />
                            <p class="text-xs text-muted-foreground">
                                Masukkan jumlah limbah sesuai hasil timbangan.
                            </p>
                            <InputError :message="form.errors.quantity" />
                        </div>

                        <div class="space-y-2">
                            <Label for="unit" class="text-sm">Satuan *</Label>
                            <Select v-model="form.unit" required>
                                <SelectTrigger id="unit">
                                    <SelectValue placeholder="Pilih satuan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="kg"
                                        >Kilogram (kg)</SelectItem
                                    >
                                    <SelectItem value="ton"
                                        >Ton metrik (ton)</SelectItem
                                    >
                                    <SelectItem value="lb"
                                        >Pound (lb)</SelectItem
                                    >
                                    <SelectItem value="g">Gram (g)</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                Gunakan satuan yang sama dengan proses
                                penimbangan.
                            </p>
                            <InputError :message="form.errors.unit" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Additional Information Card -->
            <Card class="border-slate-200 dark:border-slate-800">
                <CardContent class="space-y-4 p-6">
                    <div class="space-y-2">
                        <Label for="source" class="text-sm"
                            >Sumber / lokasi</Label
                        >
                        <Input
                            id="source"
                            v-model="form.source"
                            placeholder="Contoh: Gudang A, Jalur Produksi 2"
                        />
                        <p class="text-xs text-muted-foreground">
                            Isi asal limbah atau lokasi penemuan agar proses
                            pelacakan lebih mudah.
                        </p>
                        <InputError :message="form.errors.source" />
                    </div>

                    <div class="space-y-2">
                        <Label for="description" class="text-sm"
                            >Deskripsi</Label
                        >
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Tambahkan deskripsi singkat tentang kondisi atau jenis limbah"
                            rows="2"
                        />
                        <p class="text-xs text-muted-foreground">
                            Gunakan deskripsi singkat bila ada informasi
                            penting.
                        </p>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="notes" class="text-sm"
                            >Catatan internal</Label
                        >
                        <Textarea
                            id="notes"
                            v-model="form.notes"
                            placeholder="Catatan tambahan untuk tim operasional"
                            rows="2"
                        />
                        <p class="text-xs text-muted-foreground">
                            Catatan ini dipakai untuk kebutuhan internal.
                        </p>
                        <InputError :message="form.errors.notes" />
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <p class="mr-auto text-xs text-muted-foreground">
                    Setelah disimpan, catatan limbah akan berstatus draf dan
                    dapat ditinjau.
                </p>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                    <span v-if="form.processing">Menyimpan...</span>
                    <span v-else>Simpan catatan</span>
                </Button>
            </div>
        </form>
    </div>
</template>
