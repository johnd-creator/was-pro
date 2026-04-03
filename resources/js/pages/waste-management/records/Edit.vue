<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { format } from 'date-fns';
import {
    AlertCircle,
    CircleCheck,
    CircleX,
    LoaderCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import WasteRecordsController from '@/actions/App/Http/Controllers/WasteManagement/WasteRecordsController';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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

interface WasteRecord {
    id: string;
    record_number: string;
    date: string;
    waste_type_id: string;
    quantity: number;
    unit: string;
    source: string | null;
    description: string | null;
    notes: string | null;
    status: string;
}

type Props = {
    wasteRecord: WasteRecord;
    wasteTypes: WasteType[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Catatan Limbah',
        href: WasteRecordsController.index().url,
    },
    {
        title: props.wasteRecord.record_number,
        href: WasteRecordsController.show(props.wasteRecord.id).url,
    },
    {
        title: 'Edit',
        href: WasteRecordsController.edit(props.wasteRecord.id).url,
    },
];

const form = useForm({
    date: props.wasteRecord.date?.split('T')[0] ?? '',
    waste_type_id: props.wasteRecord.waste_type_id,
    quantity: String(props.wasteRecord.quantity),
    unit: props.wasteRecord.unit,
    source: props.wasteRecord.source ?? '',
    description: props.wasteRecord.description ?? '',
    notes: props.wasteRecord.notes ?? '',
});

const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const selectedWasteType = computed(
    () =>
        props.wasteTypes.find(
            (wasteType) => wasteType.id === form.waste_type_id,
        ) ?? null,
);

const calculatedExpiryDate = computed(() => {
    if (!form.waste_type_id || !form.date) {
        return null;
    }

    if (
        !selectedWasteType.value ||
        selectedWasteType.value.storage_period_days === 0
    ) {
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
    if (!calculatedExpiryDate.value) {
        return {
            hasExpiry: false,
            message: 'Jenis limbah ini tidak memiliki batas masa simpan.',
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
            hasExpiry: true,
            isExpired: true,
            message: `Catatan ini akan dianggap melewati batas simpan ${Math.abs(daysUntilExpiry)} hari yang lalu.`,
        };
    }

    if (daysUntilExpiry <= 7) {
        return {
            hasExpiry: true,
            isExpiringSoon: true,
            message: `Catatan ini akan mencapai batas simpan dalam ${daysUntilExpiry} hari.`,
        };
    }

    return {
        hasExpiry: true,
        isFresh: true,
        message: `Catatan ini akan mencapai batas simpan dalam ${daysUntilExpiry} hari.`,
    };
});

function getHazardousClass(isHazardous: boolean): string {
    return isHazardous
        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
}

function resetMessages(): void {
    statusMessage.value = null;
    errorMessage.value = null;
}

function submit(): void {
    resetMessages();
    form.put(WasteRecordsController.update(props.wasteRecord.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            statusMessage.value = 'Catatan limbah berhasil diperbarui.';
        },
        onError: () => {
            errorMessage.value =
                'Periksa kembali field yang ditandai lalu coba lagi.';
        },
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Edit Catatan Limbah"
    >
        <Head :title="`Edit ${wasteRecord.record_number}`" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-sky-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-28 right-0 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <Alert
                    v-if="statusMessage"
                    class="border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-100"
                >
                    <CircleCheck
                        class="h-4 w-4 !text-emerald-600 dark:!text-emerald-300"
                    />
                    <AlertTitle>Perubahan tersimpan</AlertTitle>
                    <AlertDescription>{{ statusMessage }}</AlertDescription>
                </Alert>

                <Alert v-if="errorMessage" variant="destructive">
                    <CircleX class="h-4 w-4" />
                    <AlertTitle>Pembaruan gagal</AlertTitle>
                    <AlertDescription>{{ errorMessage }}</AlertDescription>
                </Alert>

                <div
                    class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]"
                >
                    <Card
                        class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                    >
                        <CardContent class="space-y-5 p-6 lg:p-8">
                            <Heading
                                :title="`Edit ${wasteRecord.record_number}`"
                                description="Perbarui data catatan limbah sebelum diajukan ulang atau dilanjutkan ke proses berikutnya."
                            />
                            <p
                                class="-mt-5 text-sm leading-6 text-muted-foreground"
                            >
                                Pastikan jenis limbah, jumlah, dan sumber sesuai
                                dengan kondisi aktual agar proses review dan
                                transportasi berikutnya tidak terhambat.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            WasteRecordsController.show(
                                                wasteRecord.id,
                                            ).url
                                        "
                                    >
                                        Kembali ke detail
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="overflow-hidden rounded-[30px] border-slate-200/80 bg-slate-950 text-slate-50 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.45)] dark:border-slate-800"
                    >
                        <CardHeader>
                            <CardTitle class="text-xl text-white">
                                Ringkasan edit
                            </CardTitle>
                            <CardDescription class="text-slate-300">
                                Perubahan pada jenis limbah dan tanggal
                                pencatatan dapat memengaruhi batas masa simpan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <div
                                class="rounded-xl border border-white/10 bg-white p-4 dark:bg-slate-950/5"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-slate-400 uppercase"
                                >
                                    Status saat ini
                                </p>
                                <p class="mt-2 font-medium text-white">
                                    {{
                                        wasteRecord.status === 'rejected'
                                            ? 'Ditolak dan perlu diperbaiki'
                                            : 'Catatan siap diperbarui'
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="selectedWasteType"
                                class="rounded-xl border border-white/10 bg-white p-4 dark:bg-slate-950/5"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-slate-400 uppercase"
                                >
                                    Jenis limbah terpilih
                                </p>
                                <p class="mt-2 font-medium text-white">
                                    {{ selectedWasteType.name }} ({{
                                        selectedWasteType.code
                                    }})
                                </p>
                                <p class="mt-1 text-xs text-slate-300">
                                    {{
                                        selectedWasteType.category?.name ||
                                        'Tanpa kategori'
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="form.waste_type_id && form.date"
                                class="rounded-xl border p-4"
                                :class="{
                                    'border-red-900/40 bg-red-950/30':
                                        expiryInfo.isExpired,
                                    'border-orange-900/40 bg-orange-950/30':
                                        expiryInfo.isExpiringSoon,
                                    'border-emerald-900/40 bg-emerald-950/30':
                                        expiryInfo.isFresh,
                                    'border-white/10 bg-white dark:bg-slate-950/5':
                                        !expiryInfo.hasExpiry,
                                }"
                            >
                                <div class="flex items-start gap-3">
                                    <AlertCircle
                                        class="mt-0.5 h-5 w-5 text-slate-200"
                                    />
                                    <div class="flex-1">
                                        <p
                                            class="text-sm font-medium text-white"
                                        >
                                            Dampak masa simpan
                                        </p>
                                        <p class="mt-1 text-sm text-slate-200">
                                            {{ expiryInfo.message }}
                                        </p>
                                        <p
                                            v-if="
                                                calculatedExpiryDate &&
                                                expiryInfo.hasExpiry
                                            "
                                            class="mt-2 text-xs text-slate-300"
                                        >
                                            Tanggal batas simpan:
                                            {{
                                                format(
                                                    calculatedExpiryDate,
                                                    'PPP',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <Card
                        class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                    >
                        <CardHeader>
                            <CardTitle>Data utama catatan</CardTitle>
                            <CardDescription>
                                Perbarui informasi inti yang memengaruhi
                                klasifikasi, review, dan tindak lanjut
                                operasional.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-6 lg:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="date">Tanggal pencatatan</Label>
                                <Input
                                    id="date"
                                    v-model="form.date"
                                    type="date"
                                    required
                                    class="w-full"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Gunakan tanggal saat limbah benar-benar
                                    dicatat agar perhitungan masa simpan akurat.
                                </p>
                                <p
                                    v-if="form.errors.date"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.date }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="waste_type_id">Jenis limbah</Label>
                                <Select v-model="form.waste_type_id" required>
                                    <SelectTrigger id="waste_type_id">
                                        <SelectValue
                                            placeholder="Pilih jenis limbah"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="type in wasteTypes"
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
                                    Pemilihan jenis limbah akan menentukan
                                    kategori dan batas masa simpan.
                                </p>
                                <p
                                    v-if="form.errors.waste_type_id"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.waste_type_id }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="quantity">Jumlah</Label>
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
                                    Masukkan jumlah aktual agar review dan
                                    pelaporan tidak menyimpang.
                                </p>
                                <p
                                    v-if="form.errors.quantity"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.quantity }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="unit">Satuan</Label>
                                <Select v-model="form.unit" required>
                                    <SelectTrigger id="unit">
                                        <SelectValue
                                            placeholder="Pilih satuan"
                                        />
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
                                        <SelectItem value="g"
                                            >Gram (g)</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="form.errors.unit"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.unit }}
                                </p>
                            </div>

                            <div class="grid gap-2 lg:col-span-2">
                                <Label for="source">Sumber / lokasi</Label>
                                <Input
                                    id="source"
                                    v-model="form.source"
                                    placeholder="Contoh: Gudang A, Area Produksi 2"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Cantumkan lokasi atau sumber limbah agar
                                    proses verifikasi dan pengangkutan lebih
                                    jelas.
                                </p>
                                <p
                                    v-if="form.errors.source"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.source }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="wm-surface-elevated overflow-hidden rounded-[30px] dark:bg-slate-950/95"
                    >
                        <CardHeader>
                            <CardTitle>Keterangan tambahan</CardTitle>
                            <CardDescription>
                                Tambahkan konteks yang membantu approver dan tim
                                operasional memahami catatan ini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-6 lg:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="description">Deskripsi</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    placeholder="Jelaskan konteks limbah atau kondisi khusus bila diperlukan."
                                />
                                <p
                                    v-if="form.errors.description"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="notes">Catatan internal</Label>
                                <Textarea
                                    id="notes"
                                    v-model="form.notes"
                                    rows="4"
                                    placeholder="Tambahkan catatan internal untuk kebutuhan tim."
                                />
                                <p class="text-xs text-muted-foreground">
                                    Catatan internal membantu koordinasi, tetapi
                                    tidak menggantikan alasan review formal.
                                </p>
                                <p
                                    v-if="form.errors.notes"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.notes }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button type="submit" :disabled="form.processing">
                            <LoaderCircle
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Simpan perubahan
                        </Button>
                        <Button type="button" variant="ghost" as-child>
                            <Link
                                :href="
                                    WasteRecordsController.show(wasteRecord.id)
                                        .url
                                "
                            >
                                Batalkan
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </WasteManagementLayout>
</template>
