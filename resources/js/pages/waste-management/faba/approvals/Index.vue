<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarClock,
    Clock3,
    FileSpreadsheet,
    ShieldAlert,
} from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaStatus } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaPeriodSummary } from '@/types/faba';

const props = defineProps<{
    year: number;
    periods: FabaPeriodSummary[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Approval FABA',
        href: wasteManagementRoutes.faba.approvals.index.url(),
    },
];

const filterForm = reactive({
    year: props.year,
});

const totalWarnings = computed(() =>
    props.periods.reduce(
        (total, item) => total + item.recap.warnings.length,
        0,
    ),
);

const submittedPeriods = computed(
    () =>
        props.periods.filter((item) =>
            ['submitted', 'approved', 'rejected'].includes(item.status),
        ).length,
);

const readyToSubmitPeriods = computed(
    () => props.periods.filter((item) => item.can_submit).length,
);

const statusTone = (status: string): string => {
    if (status === 'approved') {
        return 'border-emerald-200/80 bg-emerald-50/90 text-emerald-700';
    }

    if (status === 'rejected') {
        return 'border-rose-200/80 bg-rose-50/90 text-rose-700';
    }

    if (status === 'submitted') {
        return 'border-amber-200/80 bg-amber-50/90 text-amber-700';
    }

    return 'border-slate-200/80 bg-white/90 text-slate-600 dark:text-slate-300';
};

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.approvals.index(), filterForm);
}

function submitPeriod(year: number, month: number): void {
    router.post(wasteManagementRoutes.faba.approvals.submit.url(), {
        year,
        month,
    });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Approval Bulanan FABA"
    >
        <Head title="Approval Bulanan FABA" />
        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[360px]"
            />
            <div
                class="pointer-events-none absolute -top-12 left-1/3 -z-10 h-56 w-56 rounded-full bg-amber-200/20 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-20 right-0 -z-10 h-64 w-64 rounded-full bg-blue-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-amber-50/25 dark:via-slate-900 dark:to-amber-950/20"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-amber-700/70 uppercase"
                                >
                                    Approval Desk
                                </p>
                                <Heading
                                    title="Approval Bulanan FABA"
                                    description="Review periode yang terbentuk dari movement bulanan, lihat warning utama, lalu kirim atau buka review detail dengan alur yang lebih cepat."
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
                                                Periode Tahun Ini
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ periods.length }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-neutral"
                                        >
                                            <CalendarClock class="h-5 w-5" />
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
                                                Siap Submit
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ readyToSubmitPeriods }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-amber"
                                        >
                                            <Clock3 class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="wm-hero-stat-card wm-hero-stat-rose"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-rose-700/80 uppercase"
                                            >
                                                Warning Aktif
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ totalWarnings }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-rose"
                                        >
                                            <ShieldAlert class="h-5 w-5" />
                                        </div>
                                    </div>
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
                                    Filter Snapshot
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Pilih tahun review
                                </p>
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Fokuskan approval ke periode aktif tanpa
                                    kehilangan konteks submit dan warning.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label>Tahun</Label>
                                <Input
                                    v-model="filterForm.year"
                                    type="number"
                                    class="h-11 bg-white dark:bg-slate-950"
                                />
                            </div>

                            <Button
                                class="w-full justify-between"
                                @click="applyFilters"
                            >
                                Terapkan Filter
                                <ArrowRight class="h-4 w-4" />
                            </Button>

                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Progress
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{ submittedPeriods }} periode sudah masuk
                                    alur submit, approve, atau reject.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Daftar Periode
                            </p>
                            <h2
                                class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                            >
                                Queue Approval
                            </h2>
                        </div>
                        <div class="wm-chip px-3 py-1.5 text-xs font-medium">
                            {{ periods.length }} periode
                        </div>
                    </div>

                    <div
                        v-if="periods.length === 0"
                        class="rounded-[26px] border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center text-sm text-slate-500 shadow-sm dark:text-slate-400"
                    >
                        Belum ada periode transaksi FABA pada tahun ini.
                    </div>

                    <div
                        v-else
                        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="item in periods"
                            :key="`${item.year}-${item.month}`"
                            class="wm-surface-elevated rounded-[26px] p-5 transition-all hover:-translate-y-0.5 hover:border-amber-200/80 hover:bg-white dark:bg-slate-950"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Periode
                                    </p>
                                    <h3
                                        class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ item.period_label }}
                                    </h3>
                                </div>
                                <span
                                    :class="[
                                        'rounded-full border px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] uppercase',
                                        statusTone(item.status),
                                    ]"
                                >
                                    {{ formatFabaStatus(item.status) }}
                                </span>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div
                                    class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-3 py-3 dark:bg-slate-900/70"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Produksi
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ item.recap.total_production }}
                                        <span
                                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                                            >ton</span
                                        >
                                    </p>
                                </div>
                                <div
                                    class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-3 py-3 dark:bg-slate-900/70"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Pemanfaatan
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ item.recap.total_utilization }}
                                        <span
                                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                                            >ton</span
                                        >
                                    </p>
                                </div>
                                <div
                                    class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-3 py-3 dark:bg-slate-900/70"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Saldo Akhir
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ item.recap.closing_balance }}
                                        <span
                                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                                            >ton</span
                                        >
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-4 rounded-[18px] border border-slate-200/80 bg-slate-50/85 px-4 py-3 dark:bg-slate-900/75"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >
                                        <FileSpreadsheet
                                            class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                        />
                                        Operasional
                                    </div>
                                    <span
                                        class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            item.operational_status ||
                                            formatFabaStatus(item.status)
                                        }}
                                    </span>
                                </div>
                                <p
                                    v-if="item.recap.warnings.length > 0"
                                    class="mt-3 text-sm text-amber-700"
                                >
                                    {{ item.recap.warnings.length }} warning
                                    perlu ditinjau sebelum periode ditutup
                                    penuh.
                                </p>
                                <p
                                    v-else
                                    class="mt-3 text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Tidak ada warning aktif pada snapshot
                                    periode ini.
                                </p>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    class="border-slate-200/80 bg-white/80 dark:bg-slate-950/75"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            wasteManagementRoutes.faba.approvals.review(
                                                [item.year, item.month],
                                            ).url
                                        "
                                    >
                                        Review Periode
                                    </Link>
                                </Button>
                                <Button
                                    v-if="item.can_submit"
                                    size="sm"
                                    class="bg-slate-950 text-white hover:bg-slate-800"
                                    @click="submitPeriod(item.year, item.month)"
                                >
                                    Submit
                                </Button>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
