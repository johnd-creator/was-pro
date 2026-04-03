<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Banknote, Layers3, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
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
import { formatFabaMaterial } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    currentBalance: number;
    canManageOpeningBalance: boolean;
    yearlyRecap: {
        year: number;
        totals: { total_production: number; total_utilization: number };
    };
    openingBalanceDefaults: {
        year: number;
        month: number;
    };
}>();

const form = useForm({
    year: props.openingBalanceDefaults.year,
    month: props.openingBalanceDefaults.month,
    material_type: 'fly_ash',
    quantity: '',
    note: '',
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Saldo TPS FABA',
        href: wasteManagementRoutes.faba.recaps.balance.url(),
    },
];

const canManageOpeningBalance = computed(() => props.canManageOpeningBalance);
const netFlow = computed(
    () =>
        props.yearlyRecap.totals.total_production -
        props.yearlyRecap.totals.total_utilization,
);

function submit(): void {
    form.post(wasteManagementRoutes.faba.recaps.openingBalance.store.url());
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Saldo TPS FABA"
    >
        <Head title="Saldo TPS FABA" />

        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-12 left-1/4 -z-10 h-56 w-56 rounded-full bg-emerald-200/20 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-20 right-0 -z-10 h-64 w-64 rounded-full bg-cyan-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-emerald-50/20 dark:via-slate-900 dark:to-emerald-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-emerald-700/70 uppercase"
                                >
                                    Balance Overview
                                </p>
                                <Heading
                                    title="Saldo TPS FABA"
                                    description="Lihat posisi saldo aktif dan arus bersih tahunan, lalu kelola opening balance dari satu panel yang lebih rapi."
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Saldo Aktif
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ currentBalance }}
                                            </p>
                                            <p
                                                class="text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                ton
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-neutral"
                                        >
                                            <Layers3 class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="wm-hero-stat-card wm-hero-stat-blue"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-blue-700/80 uppercase"
                                            >
                                                Produksi Tahun Ini
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{
                                                    yearlyRecap.totals
                                                        .total_production
                                                }}
                                            </p>
                                            <p
                                                class="text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                ton
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-blue"
                                        >
                                            <Banknote class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="wm-hero-stat-card wm-hero-stat-amber"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-amber-700/80 uppercase"
                                            >
                                                Arus Bersih
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ netFlow }}
                                            </p>
                                            <p
                                                class="text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                ton
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-amber"
                                        >
                                            <ShieldCheck class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wm-surface-panel rounded-[28px] p-5">
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Konteks Tahun
                            </p>
                            <p
                                class="mt-3 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                {{ yearlyRecap.year }}
                            </p>
                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Produksi dan pemanfaatan tahun aktif dipakai
                                sebagai pembanding agar perubahan saldo lebih
                                mudah dibaca.
                            </p>
                            <div
                                class="wm-surface-subtle mt-4 rounded-[22px] p-4"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Pemanfaatan Tahun Ini
                                </p>
                                <p
                                    class="mt-2 text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    {{ yearlyRecap.totals.total_utilization }}
                                    ton
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="canManageOpeningBalance"
                    class="wm-surface-elevated overflow-hidden rounded-[28px]"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_280px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Kendali Saldo
                                </p>
                                <h2
                                    class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Set opening balance
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Gunakan panel ini untuk menetapkan saldo
                                    awal periode saat diperlukan koreksi atau
                                    pembukaan bulan baru.
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label>Tahun</Label>
                                    <Input
                                        v-model="form.year"
                                        type="number"
                                        class="h-11"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Bulan</Label>
                                    <Input
                                        v-model="form.month"
                                        type="number"
                                        min="1"
                                        max="12"
                                        class="h-11"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Material</Label>
                                    <Select v-model="form.material_type">
                                        <SelectTrigger class="h-11"
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="fly_ash">{{
                                                formatFabaMaterial('fly_ash')
                                            }}</SelectItem>
                                            <SelectItem value="bottom_ash">{{
                                                formatFabaMaterial('bottom_ash')
                                            }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Jumlah</Label>
                                    <Input
                                        v-model="form.quantity"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="h-11"
                                    />
                                </div>
                                <div class="grid gap-2 md:col-span-2">
                                    <Label>Catatan</Label>
                                    <Textarea
                                        v-model="form.note"
                                        class="min-h-28"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="space-y-4 rounded-[24px] border border-slate-200/80 bg-slate-50/90 p-5 dark:bg-slate-900/80"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Draft Input
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    {{ form.year }} / {{ form.month }}
                                </p>
                            </div>
                            <div
                                class="rounded-[20px] border border-white/90 bg-white/90 p-4 dark:bg-slate-950/85"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Material
                                </p>
                                <p
                                    class="mt-2 text-sm font-medium text-slate-800 dark:text-slate-100"
                                >
                                    {{ formatFabaMaterial(form.material_type) }}
                                </p>
                            </div>
                            <div
                                class="rounded-[20px] border border-white/90 bg-white/90 p-4 dark:bg-slate-950/85"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Jumlah
                                </p>
                                <p
                                    class="mt-2 text-sm font-medium text-slate-800 dark:text-slate-100"
                                >
                                    {{ form.quantity || '0' }} ton
                                </p>
                            </div>
                            <Button
                                class="w-full justify-between"
                                :disabled="form.processing"
                                @click="submit"
                            >
                                Simpan opening balance
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </section>

                <section
                    v-else
                    class="rounded-[26px] border border-dashed border-slate-200 bg-white/85 px-6 py-10 text-sm leading-6 text-slate-600 shadow-sm dark:text-slate-300"
                >
                    Anda dapat melihat saldo TPS, tetapi perubahan opening
                    balance hanya tersedia untuk role yang memiliki izin
                    pengelolaan saldo awal.
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
