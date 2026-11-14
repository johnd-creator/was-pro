<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    Factory,
    FileText,
    Layers3,
    Save,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';
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
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    materialOptions: string[];
    movementTypeOptionsByMaterial: Record<string, string[]>;
    defaultUnit: string;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Produksi FABA',
        href: wasteManagementRoutes.faba.production.index.url(),
    },
    {
        title: 'Tambah',
        href: wasteManagementRoutes.faba.production.create.url(),
    },
];

const form = useForm<{
    transaction_date: string;
    material_type: string;
    movement_type: string;
    quantity: string;
    unit: string;
    note: string;
}>({
    transaction_date: formatLocalDate(),
    material_type: '',
    movement_type: '',
    quantity: '',
    unit: props.defaultUnit,
    note: '',
});

function submit(): void {
    form.post(scopedUrl(wasteManagementRoutes.faba.production.store.url()));
}

const movementTypeOptions = computed(
    () => props.movementTypeOptionsByMaterial[form.material_type] ?? [],
);

watch(
    () => form.material_type,
    () => {
        if (!movementTypeOptions.value.includes(form.movement_type)) {
            form.movement_type = '';
        }
    },
);
const { scopedUrl } = useOrganizationContext();
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Tambah Produksi FABA"
    >
        <Head title="Tambah Produksi FABA" />

        <div class="relative min-w-0 overflow-x-hidden p-4">
            <div class="mx-auto max-w-5xl space-y-4">
                <section class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="space-y-4">
                            <Heading title="Tambah Produksi FABA" />

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tanggal Input
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ form.transaction_date }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-blue"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-blue-700/80 uppercase"
                                    >
                                        Material
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            form.material_type ||
                                            'Belum dipilih'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
                                    >
                                        Tipe
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            form.movement_type ||
                                            'Belum dipilih'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="wm-surface-panel space-y-4 rounded-[28px] p-5"
                        >
                            <div class="space-y-2">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Panduan Cepat
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Checklist input
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div
                                    class="flex items-start gap-3 rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                >
                                    <CalendarClock
                                        class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                    />
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Pastikan tanggal transaksi sesuai
                                        movement aktual periode produksi.
                                    </p>
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                >
                                    <Layers3
                                        class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                    />
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Material dan tipe harus konsisten agar
                                        recap bulanan tidak perlu koreksi ulang.
                                    </p>
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                >
                                    <FileText
                                        class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                    />
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Tambahkan catatan singkat bila ada
                                        konteks shift atau sumber produksi
                                        khusus.
                                    </p>
                                </div>
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
                                                class="rounded-2xl bg-blue-100 p-3 text-blue-700"
                                            >
                                                <Factory class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    Identitas Produksi
                                                </p>
                                                <p
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    Atur tanggal, material, dan
                                                    tipe movement.
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
                                                        >
                                                            <SelectValue
                                                                placeholder="Pilih material"
                                                            />
                                                        </SelectTrigger>
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
                                                    <InputError
                                                        :message="
                                                            form.errors
                                                                .material_type
                                                        "
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label>Tipe movement</Label>
                                                    <Select
                                                        v-model="
                                                            form.movement_type
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="bg-white dark:bg-slate-950"
                                                        >
                                                            <SelectValue
                                                                placeholder="Pilih tipe"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="item in movementTypeOptions"
                                                                :key="item"
                                                                :value="item"
                                                            >
                                                                {{ item }}
                                                            </SelectItem>
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
                                                class="rounded-2xl bg-emerald-100 p-3 text-emerald-700"
                                            >
                                                <Layers3 class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    Volume dan Catatan
                                                </p>
                                                <p
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    Lengkapi quantity dan
                                                    konteks operasional bila
                                                    diperlukan.
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
                                                    <p
                                                        class="text-sm text-slate-500 dark:text-slate-400"
                                                    >
                                                        Satuan transaksi
                                                        ditetapkan ton.
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="note"
                                                    >Catatan</Label
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
                                                    Tipe movement
                                                </p>
                                                <p
                                                    class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    {{
                                                        form.movement_type ||
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
                                        Simpan produksi
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
