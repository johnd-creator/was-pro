<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarClock, FileText, ShieldCheck, Wrench } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    entry: {
        id: string;
        display_number: string;
        transaction_date: string;
        material_type: string;
        movement_type: string;
        quantity: number;
        unit: string;
        note: string | null;
        period_label: string;
        approval_status: string;
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Adjustment FABA',
        href: wasteManagementRoutes.faba.adjustments.index.url(),
    },
    {
        title: props.entry.display_number,
        href: wasteManagementRoutes.faba.adjustments.show(props.entry.id).url,
    },
];
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Detail Adjustment FABA"
    >
        <Head title="Detail Adjustment FABA" />
        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[320px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-orange-200/15 blur-3xl"
            />

            <div class="mx-auto max-w-5xl space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-orange-50/20 dark:via-slate-900 dark:to-orange-950/18"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:p-6"
                    >
                        <div class="space-y-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-orange-700/70 uppercase"
                            >
                                Adjustment Detail
                            </p>
                            <Heading
                                :title="entry.display_number"
                                description="Detail movement adjustment FABA dengan konteks periode dan status approval."
                            />
                            <div class="flex flex-wrap gap-3">
                                <Badge
                                    variant="secondary"
                                    class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700 dark:text-slate-200"
                                    >{{
                                        formatFabaStatus(entry.approval_status)
                                    }}</Badge
                                >
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    {{ entry.period_label }}
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3">
                            <div
                                class="rounded-[22px] border border-orange-200/80 bg-orange-50/90 px-4 py-4 shadow-sm shadow-orange-100/60"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-orange-700/80 uppercase"
                                >
                                    Quantity
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                >
                                    {{ entry.quantity }} {{ entry.unit }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <Card
                    class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                >
                    <CardHeader
                        ><CardTitle>Informasi Utama</CardTitle></CardHeader
                    >
                    <CardContent class="grid gap-4 lg:grid-cols-2">
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <div class="flex items-center gap-3">
                                <CalendarClock
                                    class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tanggal
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaDate(
                                                entry.transaction_date,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <div class="flex items-center gap-3">
                                <Wrench
                                    class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tipe
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaMovementType(
                                                entry.movement_type,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Material
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ formatFabaMaterial(entry.material_type) }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <div class="flex items-center gap-3">
                                <ShieldCheck
                                    class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Status
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            formatFabaStatus(
                                                entry.approval_status,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Jumlah
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.quantity }} {{ entry.unit }}
                            </p>
                        </div>
                        <div class="wm-surface-subtle rounded-[20px] p-4">
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Periode
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-100"
                            >
                                {{ entry.period_label }}
                            </p>
                        </div>
                        <div
                            class="wm-surface-subtle rounded-[20px] p-4 lg:col-span-2"
                        >
                            <div class="flex items-start gap-3">
                                <FileText
                                    class="mt-0.5 h-4 w-4 text-slate-500 dark:text-slate-400"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Alasan koreksi
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-6 text-slate-700 dark:text-slate-200"
                                    >
                                        {{ entry.note || '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
