<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarClock, FileText, Save, Wrench } from 'lucide-vue-next';
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
import Heading from '@/components/waste-management/CompactPageHeading.vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatLocalDate } from '@/lib/date';
import { formatFabaMaterial, formatFabaMovementType } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    materialOptions: string[];
    movementTypeOptions: string[];
    defaultUnit: string;
    sourceOptions: {
        id: string;
        transaction_date: string;
        material_type: string;
        movement_type: string;
        quantity: number;
        unit: string;
    }[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Adjustment FABA',
        href: wasteManagementRoutes.faba.adjustments.index.url(),
    },
    {
        title: 'Tambah',
        href: wasteManagementRoutes.faba.adjustments.create.url(),
    },
];

const form = useForm({
    transaction_date: formatLocalDate(),
    material_type: '',
    movement_type: '',
    quantity: '',
    unit: props.defaultUnit,
    note: '',
    source_movement_id: '',
});

function submit(): void {
    form.post(scopedUrl(wasteManagementRoutes.faba.adjustments.store.url()));
}
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Tambah Adjustment FABA"
    >
        <Head title="Tambah Adjustment FABA" />
        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="mx-auto max-w-5xl space-y-4">
                <section class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="space-y-4">
                            <Heading title="Tambah Adjustment FABA" />
                        </div>
                        <div
                            class="wm-surface-panel space-y-3 rounded-[28px] p-5"
                        >
                            <div
                                class="flex items-start gap-3 rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                            >
                                <Wrench
                                    class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Gunakan adjustment hanya saat ada koreksi
                                    nyata yang tidak bisa diselesaikan lewat
                                    entry biasa.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <form class="space-y-4" @submit.prevent="submit">
                    <Card
                        class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                    >
                        <CardContent class="space-y-4 px-4 py-0">
                            <div
                                class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]"
                            >
                                <div class="space-y-6">
                                    <div
                                        class="rounded-[22px] border border-slate-200/80 bg-slate-50/70 p-5 dark:bg-slate-900/65"
                                    >
                                        <div
                                            class="mb-4 flex items-center gap-3"
                                        >
                                            <div
                                                class="rounded-2xl bg-orange-100 p-3 text-orange-700"
                                            >
                                                <CalendarClock
                                                    class="h-5 w-5"
                                                />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    Identitas Koreksi
                                                </p>
                                                <p
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    Tentukan tanggal, material,
                                                    dan tipe koreksi.
                                                </p>
                                            </div>
                                        </div>
                                        <div class="grid gap-6">
                                            <div class="grid gap-2">
                                                <Label for="transaction_date"
                                                    >Tanggal transaksi</Label
                                                >
                                                <Input
                                                    id="transaction_date"
                                                    v-model="
                                                        form.transaction_date
                                                    "
                                                    type="date"
                                                    class="bg-white dark:bg-slate-950"
                                                />
                                                <InputError
                                                    :message="
                                                        form.errors
                                                            .transaction_date
                                                    "
                                                />
                                            </div>
                                            <div
                                                class="grid gap-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label>Material</Label>
                                                    <Select
                                                        v-model="
                                                            form.material_type
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="bg-white dark:bg-slate-950"
                                                            ><SelectValue
                                                                placeholder="Pilih material"
                                                        /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="item in materialOptions"
                                                                :key="item"
                                                                :value="item"
                                                                >{{
                                                                    formatFabaMaterial(
                                                                        item,
                                                                    )
                                                                }}</SelectItem
                                                            >
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        :message="
                                                            form.errors
                                                                .material_type
                                                        "
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        >Tipe adjustment</Label
                                                    >
                                                    <Select
                                                        v-model="
                                                            form.movement_type
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="bg-white dark:bg-slate-950"
                                                            ><SelectValue
                                                                placeholder="Pilih tipe adjustment"
                                                        /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="item in movementTypeOptions"
                                                                :key="item"
                                                                :value="item"
                                                                >{{
                                                                    formatFabaMovementType(
                                                                        item,
                                                                    )
                                                                }}</SelectItem
                                                            >
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        :message="
                                                            form.errors
                                                                .movement_type
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[22px] border border-slate-200/80 bg-slate-50/70 p-5 dark:bg-slate-900/65"
                                    >
                                        <div
                                            class="mb-4 flex items-center gap-3"
                                        >
                                            <div
                                                class="wm-hero-icon wm-hero-icon-neutral"
                                            >
                                                <FileText class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    Volume dan Alasan
                                                </p>
                                                <p
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    Jelaskan koreksi agar
                                                    approval dan audit mudah
                                                    dibaca.
                                                </p>
                                            </div>
                                        </div>
                                        <div class="grid gap-6">
                                            <div
                                                class="grid gap-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label for="quantity"
                                                        >Jumlah</Label
                                                    >
                                                    <Input
                                                        id="quantity"
                                                        v-model="form.quantity"
                                                        type="number"
                                                        step="0.01"
                                                        class="bg-white dark:bg-slate-950"
                                                    />
                                                    <InputError
                                                        :message="
                                                            form.errors.quantity
                                                        "
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label for="unit"
                                                        >Satuan</Label
                                                    >
                                                    <Input
                                                        id="unit"
                                                        v-model="form.unit"
                                                        readonly
                                                        class="bg-white dark:bg-slate-950"
                                                    />
                                                </div>
                                            </div>
                                            <div class="grid gap-2">
                                                <Label for="source_movement_id"
                                                    >Transaksi asal
                                                    (opsional)</Label
                                                >
                                                <select
                                                    id="source_movement_id"
                                                    v-model="
                                                        form.source_movement_id
                                                    "
                                                    class="h-11 rounded-md border bg-background px-3"
                                                >
                                                    <option value="">
                                                        Tanpa tautan transaksi
                                                        asal
                                                    </option>
                                                    <option
                                                        v-for="source in sourceOptions.filter(
                                                            (item) =>
                                                                item.material_type ===
                                                                form.material_type,
                                                        )"
                                                        :key="source.id"
                                                        :value="source.id"
                                                    >
                                                        {{
                                                            source.transaction_date.slice(
                                                                0,
                                                                10,
                                                            )
                                                        }}
                                                        Â·
                                                        {{
                                                            formatFabaMovementType(
                                                                source.movement_type,
                                                            )
                                                        }}
                                                        Â· {{ source.quantity }}
                                                        {{ source.unit }} Â·
                                                        {{ source.id }}
                                                    </option>
                                                </select>
                                                <p
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    Tautan ini menyimpan bukti
                                                    transaksi asal, bukan
                                                    menghitung pembalikan
                                                    otomatis.
                                                </p>
                                                <InputError
                                                    :message="
                                                        form.errors
                                                            .source_movement_id
                                                    "
                                                />
                                            </div>
                                            <div class="grid gap-2">
                                                <Label for="note"
                                                    >Alasan koreksi</Label
                                                >
                                                <Textarea
                                                    id="note"
                                                    v-model="form.note"
                                                    class="min-h-28 bg-white dark:bg-slate-950"
                                                />
                                                <InputError
                                                    :message="form.errors.note"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div
                                        class="rounded-[22px] border border-slate-200/80 bg-white/90 p-5 shadow-sm shadow-slate-100/60 dark:bg-slate-950/85"
                                    >
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Preview
                                        </p>
                                        <div class="mt-4 space-y-3">
                                            <div
                                                class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                            >
                                                <p
                                                    class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Material
                                                </p>
                                                <p
                                                    class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    {{
                                                        form.material_type ||
                                                        'Belum dipilih'
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                            >
                                                <p
                                                    class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Quantity
                                                </p>
                                                <p
                                                    class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    {{ form.quantity || '0' }}
                                                    {{ form.unit }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <Button
                                        type="submit"
                                        class="w-full justify-between"
                                        :disabled="form.processing"
                                    >
                                        Simpan adjustment
                                        <Save class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </div>
    </WasteManagementLayout>
</template>
