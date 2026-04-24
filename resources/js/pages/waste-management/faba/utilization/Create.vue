<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Building2,
    CalendarClock,
    FileText,
    Save,
    ShieldCheck,
} from 'lucide-vue-next';
import { watch } from 'vue';
import Heading from '@/components/Heading.vue';
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
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaMovementType } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaInternalDestination,
    FabaPurpose,
    FabaVendor,
} from '@/types/faba';

const props = defineProps<{
    vendors: FabaVendor[];
    internalDestinations: FabaInternalDestination[];
    purposes: FabaPurpose[];
    materialOptions: string[];
    movementTypeOptions: string[];
    initialMovementType: string;
    defaultUnit: string;
    requirements: Record<
        string,
        {
            requiresVendor: boolean;
            requiresDocument: boolean;
            requiresInternalDestination: boolean;
        }
    >;
}>();

const NO_VENDOR = '__none__';
const NO_INTERNAL_DESTINATION = '__none__';
const NO_PURPOSE = '__none__';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pemanfaatan FABA',
        href: wasteManagementRoutes.faba.utilization.index.url(),
    },
    {
        title: 'Tambah',
        href: wasteManagementRoutes.faba.utilization.create.url(),
    },
];

const form = useForm({
    transaction_date: new Date().toISOString().split('T')[0],
    material_type: '',
    movement_type: props.initialMovementType,
    vendor_id: NO_VENDOR,
    internal_destination_id: NO_INTERNAL_DESTINATION,
    purpose_id: NO_PURPOSE,
    quantity: '',
    unit: props.defaultUnit,
    document_number: '',
    document_date: '',
    attachment: null as File | null,
    note: '',
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        vendor_id:
            data.movement_type === 'utilization_internal' ||
            data.vendor_id === NO_VENDOR
                ? null
                : data.vendor_id,
        internal_destination_id:
            data.movement_type === 'utilization_external' ||
            data.internal_destination_id === NO_INTERNAL_DESTINATION
                ? null
                : data.internal_destination_id,
        purpose_id: data.purpose_id === NO_PURPOSE ? null : data.purpose_id,
    })).post(wasteManagementRoutes.faba.utilization.store.url());
}

function updateAttachment(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

watch(
    () => form.movement_type,
    (value) => {
        if (value === 'utilization_internal') {
            form.vendor_id = NO_VENDOR;
            form.document_number = '';
            form.document_date = '';
        } else if (value === 'utilization_external') {
            form.internal_destination_id = NO_INTERNAL_DESTINATION;
        }
    },
);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Tambah Pemanfaatan FABA"
    >
        <Head title="Tambah Pemanfaatan FABA" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[360px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-24 right-0 -z-10 h-56 w-56 rounded-full bg-amber-200/15 blur-3xl"
            />

            <div class="mx-auto max-w-5xl space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-emerald-50/20 dark:via-slate-900 dark:to-emerald-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                            >
                                Smart Utilization
                            </p>
                            <Heading
                                title="Tambah Pemanfaatan FABA"
                                description="Catat pemanfaatan internal atau eksternal dari satu form dinamis agar kebutuhan pihak, tujuan, dan dokumen tetap sinkron."
                            />

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tanggal
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ form.transaction_date }}
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
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
                                    class="wm-hero-stat-card wm-hero-stat-amber"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-amber-700/80 uppercase"
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
                                    Jalur validasi
                                </p>
                            </div>
                            <div class="space-y-3">
                                <div
                                    class="flex items-start gap-3 rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                >
                                    <ArrowLeftRight
                                        class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                    />
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Pilih jalur internal atau eksternal
                                        lebih dulu agar requirement field ikut
                                        tepat.
                                    </p>
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                >
                                    <ShieldCheck
                                        class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                    />
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        Dokumen vendor wajib bila transaksi
                                        mengarah ke pemanfaatan eksternal.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <form class="space-y-6" @submit.prevent="submit">
                    <Card
                        class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                    >
                        <CardContent class="space-y-6 p-6">
                            <div
                                class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]"
                            >
                                <div class="space-y-6">
                                    <div
                                        class="rounded-[22px] border border-slate-200/80 bg-slate-50/70 p-5 dark:bg-slate-900/65"
                                    >
                                        <div
                                            class="mb-4 flex items-center gap-3"
                                        >
                                            <div
                                                class="rounded-2xl bg-emerald-100 p-3 text-emerald-700"
                                            >
                                                <CalendarClock
                                                    class="h-5 w-5"
                                                />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    Identitas Movement
                                                </p>
                                                <p
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    Atur tanggal, material, dan
                                                    jenis pemanfaatan.
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
                                                                    item
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
                                                        >Tipe pemanfaatan</Label
                                                    >
                                                    <Select
                                                        v-model="
                                                            form.movement_type
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="bg-white dark:bg-slate-950"
                                                            ><SelectValue
                                                                placeholder="Pilih tipe"
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
                                                class="rounded-2xl bg-blue-100 p-3 text-blue-700"
                                            >
                                                <Building2 class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    Pihak dan Volume
                                                </p>
                                                <p
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    Pilih tujuan atau vendor
                                                    lalu isi quantity movement.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid gap-6">
                                            <div
                                                class="grid gap-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label>
                                                        {{
                                                            form.movement_type ===
                                                            'utilization_internal'
                                                                ? 'Tujuan internal'
                                                                : 'Vendor'
                                                        }}
                                                    </Label>
                                                    <Select
                                                        v-if="
                                                            form.movement_type ===
                                                            'utilization_internal'
                                                        "
                                                        v-model="
                                                            form.internal_destination_id
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="bg-white dark:bg-slate-950"
                                                            ><SelectValue
                                                                placeholder="Pilih tujuan internal"
                                                        /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                :value="
                                                                    NO_INTERNAL_DESTINATION
                                                                "
                                                                >Pilih tujuan
                                                                internal</SelectItem
                                                            >
                                                            <SelectItem
                                                                v-for="destination in internalDestinations"
                                                                :key="
                                                                    destination.id
                                                                "
                                                                :value="
                                                                    destination.id
                                                                "
                                                                >{{
                                                                    destination.name
                                                                }}</SelectItem
                                                            >
                                                        </SelectContent>
                                                    </Select>
                                                    <Select
                                                        v-else
                                                        v-model="form.vendor_id"
                                                    >
                                                        <SelectTrigger
                                                            class="bg-white dark:bg-slate-950"
                                                            ><SelectValue
                                                                placeholder="Pilih vendor bila eksternal"
                                                        /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                :value="
                                                                    NO_VENDOR
                                                                "
                                                                >Tanpa
                                                                vendor</SelectItem
                                                            >
                                                            <SelectItem
                                                                v-for="vendor in vendors"
                                                                :key="vendor.id"
                                                                :value="
                                                                    vendor.id
                                                                "
                                                                >{{
                                                                    vendor.name
                                                                }}</SelectItem
                                                            >
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        :message="
                                                            form.movement_type ===
                                                            'utilization_internal'
                                                                ? form.errors
                                                                      .internal_destination_id
                                                                : form.errors
                                                                      .vendor_id
                                                        "
                                                    />
                                                    <p
                                                        class="text-sm text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            props.requirements[
                                                                form
                                                                    .movement_type
                                                            ]?.requiresVendor
                                                                ? 'Vendor wajib untuk pemanfaatan eksternal.'
                                                                : 'Tujuan internal wajib dipilih untuk pemanfaatan internal.'
                                                        }}
                                                    </p>
                                                </div>
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
                                            </div>
                                            <div
                                                class="grid gap-6 md:grid-cols-2"
                                            >
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
                                                <div class="grid gap-2">
                                                    <Label
                                                        >Use-case /
                                                        purpose</Label
                                                    >
                                                    <Select
                                                        v-model="
                                                            form.purpose_id
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="bg-white dark:bg-slate-950"
                                                            ><SelectValue
                                                                placeholder="Pilih use-case (opsional)"
                                                        /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                :value="
                                                                    NO_PURPOSE
                                                                "
                                                                >Tanpa
                                                                use-case</SelectItem
                                                            >
                                                            <SelectItem
                                                                v-for="purpose in purposes"
                                                                :key="
                                                                    purpose.id
                                                                "
                                                                :value="
                                                                    purpose.id
                                                                "
                                                                >{{
                                                                    purpose.name
                                                                }}</SelectItem
                                                            >
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        :message="
                                                            form.errors
                                                                .purpose_id
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
                                                class="rounded-2xl bg-amber-100 p-3 text-amber-700"
                                            >
                                                <FileText class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    Dokumen dan Catatan
                                                </p>
                                                <p
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    Lengkapi metadata dokumen
                                                    terutama untuk jalur
                                                    eksternal.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid gap-6">
                                            <div
                                                class="grid gap-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label for="document_number"
                                                        >Nomor dokumen</Label
                                                    >
                                                    <Input
                                                        id="document_number"
                                                        v-model="
                                                            form.document_number
                                                        "
                                                        class="bg-white dark:bg-slate-950"
                                                    />
                                                    <p
                                                        class="text-sm text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            props.requirements[
                                                                form
                                                                    .movement_type
                                                            ]?.requiresDocument
                                                                ? 'Nomor dokumen wajib untuk transaksi eksternal.'
                                                                : 'Dokumen eksternal tidak diperlukan untuk pemanfaatan internal.'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label for="document_date"
                                                        >Tanggal dokumen</Label
                                                    >
                                                    <Input
                                                        id="document_date"
                                                        v-model="
                                                            form.document_date
                                                        "
                                                        type="date"
                                                        class="bg-white dark:bg-slate-950"
                                                    />
                                                    <p
                                                        v-if="
                                                            props.requirements[
                                                                form
                                                                    .movement_type
                                                            ]?.requiresDocument
                                                        "
                                                        class="text-sm text-slate-500 dark:text-slate-400"
                                                    >
                                                        Tanggal dokumen wajib
                                                        untuk transaksi
                                                        eksternal.
                                                    </p>
                                                </div>
                                            </div>
                                            <div
                                                class="grid gap-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label for="attachment"
                                                        >Lampiran</Label
                                                    >
                                                    <Input
                                                        id="attachment"
                                                        type="file"
                                                        class="bg-white dark:bg-slate-950"
                                                        @change="
                                                            updateAttachment
                                                        "
                                                    />
                                                    <InputError
                                                        :message="
                                                            form.errors
                                                                .attachment
                                                        "
                                                    />
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
                                                </div>
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
                                                    Jalur
                                                </p>
                                                <p
                                                    class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    {{
                                                        formatFabaMovementType(
                                                            form.movement_type,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                            >
                                                <p
                                                    class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Pihak
                                                </p>
                                                <p
                                                    class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                                >
                                                    {{
                                                        form.movement_type ===
                                                        'utilization_internal'
                                                            ? internalDestinations.find(
                                                                  (item) =>
                                                                      item.id ===
                                                                      form.internal_destination_id,
                                                              )?.name ||
                                                              'Belum dipilih'
                                                            : vendors.find(
                                                                  (item) =>
                                                                      item.id ===
                                                                      form.vendor_id,
                                                              )?.name ||
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
                                        Simpan pemanfaatan
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
