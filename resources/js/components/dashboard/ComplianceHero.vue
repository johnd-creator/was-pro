<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    ShieldAlert,
    ShieldCheck,
    Timer,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import wasteManagementRoutes from '@/routes/waste-management';

interface Props {
    expiredWaste: number;
    expiringSoonWaste: number;
    pendingWasteApprovals: number;
    pendingFabaApprovals: number;
    fabaWarnings: number;
    riskStatus: 'normal' | 'warning' | 'critical';
    riskLabel: string;
    riskTone: 'green' | 'orange' | 'red';
    showMetricCards?: boolean;
}

interface HeroTone {
    badgeClass: string;
    shellClass: string;
    buttonClass: string;
    railClass: string;
    ringClass: string;
}

interface MetricCard {
    title: string;
    value: number;
    description: string;
    icon: LucideIcon;
    shellClass: string;
    valueClass: string;
    iconWrapClass: string;
    iconClass: string;
    statusText: string;
    barClass: string;
}

const props = withDefaults(defineProps<Props>(), {
    showMetricCards: false,
});

const primaryMessage = computed(() => {
    if (props.expiredWaste > 0) {
        return `${props.expiredWaste} catatan limbah sudah melewati batas simpan dan perlu penanganan segera.`;
    }

    if (props.fabaWarnings > 0) {
        return `${props.fabaWarnings} periode FABA memerlukan perhatian karena warning saldo atau konsistensi data.`;
    }

    if (props.pendingWasteApprovals + props.pendingFabaApprovals > 0) {
        return `Terdapat ${props.pendingWasteApprovals + props.pendingFabaApprovals} antrian approval yang perlu diputuskan untuk menjaga ritme operasional tetap aman.`;
    }

    if (props.expiringSoonWaste > 0) {
        return `${props.expiringSoonWaste} catatan limbah mendekati batas simpan dalam tujuh hari ke depan.`;
    }

    return 'Kepatuhan operasional berada dalam kondisi terkendali dan tidak ada anomali utama yang perlu ditindaklanjuti saat ini.';
});

const totalRiskCount = computed(
    () =>
        props.expiredWaste +
        props.expiringSoonWaste +
        props.pendingWasteApprovals +
        props.pendingFabaApprovals +
        props.fabaWarnings,
);

const totalApprovalCount = computed(
    () => props.pendingWasteApprovals + props.pendingFabaApprovals,
);

const bannerBadgeLabel = computed(() => `Kepatuhan ${props.riskLabel}`);
const actionLabel = computed(() =>
    props.riskStatus === 'critical'
        ? 'Tinjau Catatan Kritis'
        : 'Tinjau Catatan',
);

const toneMap: Record<Props['riskTone'], HeroTone> = {
    red: {
        badgeClass:
            'border border-red-300/90 bg-red-100 text-red-800 dark:border-red-900/70 dark:bg-red-950/50 dark:text-red-200',
        shellClass:
            'border-red-300/80 bg-linear-to-br from-red-50 via-white to-red-50/70 dark:border-red-900/70 dark:from-red-950/45 dark:via-slate-950 dark:to-red-950/20',
        buttonClass:
            'border-red-200 bg-red-600 text-white hover:bg-red-700 dark:border-red-900/60 dark:bg-red-700 dark:hover:bg-red-600',
        railClass: 'bg-red-600 dark:bg-red-500',
        ringClass: 'ring-1 ring-red-200/80 dark:ring-red-900/40',
    },
    orange: {
        badgeClass:
            'border border-amber-300/90 bg-amber-100 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/50 dark:text-amber-200',
        shellClass:
            'border-amber-300/80 bg-linear-to-br from-amber-50 via-white to-orange-50/70 dark:border-amber-900/70 dark:from-amber-950/35 dark:via-slate-950 dark:to-orange-950/20',
        buttonClass:
            'border-amber-200 bg-amber-600 text-white hover:bg-amber-700 dark:border-amber-900/60 dark:bg-amber-700 dark:hover:bg-amber-600',
        railClass: 'bg-amber-500 dark:bg-amber-400',
        ringClass: 'ring-1 ring-amber-200/80 dark:ring-amber-900/40',
    },
    green: {
        badgeClass:
            'border border-emerald-300/90 bg-emerald-100 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/50 dark:text-emerald-200',
        shellClass:
            'border-emerald-300/80 bg-linear-to-br from-emerald-50 via-white to-teal-50/60 dark:border-emerald-900/70 dark:from-emerald-950/28 dark:via-slate-950 dark:to-teal-950/16',
        buttonClass:
            'border-emerald-200 bg-emerald-600 text-white hover:bg-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-700 dark:hover:bg-emerald-600',
        railClass: 'bg-emerald-500 dark:bg-emerald-400',
        ringClass: 'ring-1 ring-emerald-200/80 dark:ring-emerald-900/40',
    },
};

const tone = computed(() => toneMap[props.riskTone]);

const statusIcon = computed<LucideIcon>(() => {
    if (props.riskStatus === 'critical') {
        return ShieldAlert;
    }

    if (props.riskStatus === 'warning') {
        return AlertTriangle;
    }

    return ShieldCheck;
});

const metricCards = computed<MetricCard[]>(() => [
    {
        title: 'Melewati Batas Simpan',
        value: props.expiredWaste,
        description: 'Catatan perlu tindakan',
        icon: ShieldAlert,
        shellClass:
            'border-red-300/90 bg-red-50/90 dark:border-red-900/70 dark:bg-red-950/30',
        valueClass: 'text-red-700 dark:text-red-300',
        iconWrapClass:
            'border-red-200 bg-red-100 dark:border-red-900/60 dark:bg-red-950/50',
        iconClass: 'text-red-700 dark:text-red-300',
        statusText:
            props.expiredWaste > 0
                ? 'Prioritas tertinggi'
                : 'Tidak ada catatan kritis',
        barClass: 'bg-red-600 dark:bg-red-400',
    },
    {
        title: 'Mendekati Batas Simpan',
        value: props.expiringSoonWaste,
        description: 'Dalam 7 hari',
        icon: Timer,
        shellClass:
            'border-amber-300/90 bg-amber-50/90 dark:border-amber-900/70 dark:bg-amber-950/25',
        valueClass: 'text-amber-700 dark:text-amber-300',
        iconWrapClass:
            'border-amber-200 bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/45',
        iconClass: 'text-amber-700 dark:text-amber-300',
        statusText:
            props.expiringSoonWaste > 0
                ? 'Perlu penjadwalan cepat'
                : 'Tidak ada catatan mendekati batas',
        barClass: 'bg-amber-500 dark:bg-amber-400',
    },
    {
        title: 'Pending Approval',
        value: totalApprovalCount.value,
        description: `${props.pendingWasteApprovals} limbah, ${props.pendingFabaApprovals} FABA`,
        icon: AlertTriangle,
        shellClass:
            totalApprovalCount.value > 0
                ? 'border-orange-300/90 bg-orange-50/90 dark:border-orange-900/70 dark:bg-orange-950/25'
                : 'border-slate-200/90 bg-slate-50/90 dark:border-slate-800 dark:bg-slate-900/40',
        valueClass:
            totalApprovalCount.value > 0
                ? 'text-orange-700 dark:text-orange-300'
                : 'text-slate-900 dark:text-slate-100',
        iconWrapClass:
            totalApprovalCount.value > 0
                ? 'border-orange-200 bg-orange-100 dark:border-orange-900/60 dark:bg-orange-950/45'
                : 'border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900',
        iconClass:
            totalApprovalCount.value > 0
                ? 'text-orange-700 dark:text-orange-300'
                : 'text-slate-600 dark:text-slate-300',
        statusText:
            totalApprovalCount.value > 0
                ? 'Keputusan tertunda aktif'
                : 'Queue approval terkendali',
        barClass:
            totalApprovalCount.value > 0
                ? 'bg-orange-500 dark:bg-orange-400'
                : 'bg-slate-300 dark:bg-slate-700',
    },
    {
        title: 'Peringatan FABA',
        value: props.fabaWarnings,
        description: 'Periode bermasalah',
        icon: AlertTriangle,
        shellClass:
            props.fabaWarnings > 0
                ? 'border-rose-300/90 bg-rose-50/85 dark:border-rose-900/70 dark:bg-rose-950/25'
                : 'border-slate-200/90 bg-slate-50/90 dark:border-slate-800 dark:bg-slate-900/40',
        valueClass:
            props.fabaWarnings > 0
                ? 'text-rose-700 dark:text-rose-300'
                : 'text-slate-900 dark:text-slate-100',
        iconWrapClass:
            props.fabaWarnings > 0
                ? 'border-rose-200 bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/45'
                : 'border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900',
        iconClass:
            props.fabaWarnings > 0
                ? 'text-rose-700 dark:text-rose-300'
                : 'text-slate-600 dark:text-slate-300',
        statusText:
            props.fabaWarnings > 0
                ? 'Butuh telaah data FABA'
                : 'Tidak ada warning aktif',
        barClass:
            props.fabaWarnings > 0
                ? 'bg-rose-500 dark:bg-rose-400'
                : 'bg-slate-300 dark:bg-slate-700',
    },
]);
</script>

<template>
    <div class="space-y-3">
        <div
            :class="[
                'relative overflow-hidden rounded-2xl border shadow-sm transition-all duration-200',
                tone.shellClass,
                tone.ringClass,
            ]"
        >
            <div
                class="absolute inset-y-0 left-0 w-1.5"
                :class="tone.railClass"
            />
            <div
                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0 flex-1">
                    <div
                        :class="[
                            'inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-[0.12em] uppercase',
                            tone.badgeClass,
                        ]"
                    >
                        <component :is="statusIcon" class="size-3.5" />
                        {{ bannerBadgeLabel }}
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2.5">
                        <p
                            class="wm-text-primary text-[15px] leading-6 font-semibold sm:text-base"
                        >
                            {{ primaryMessage }}
                        </p>
                        <p class="wm-text-secondary text-xs font-medium">
                            {{ totalRiskCount }} indikator aktif
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center">
                    <Button
                        as-child
                        class="h-9 rounded-lg px-4"
                        :class="tone.buttonClass"
                    >
                        <Link
                            :href="
                                wasteManagementRoutes.records.pendingApproval()
                                    .url
                            "
                        >
                            {{ actionLabel }}
                            <ArrowRight class="ml-2 size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="showMetricCards"
            class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
        >
            <div
                v-for="card in metricCards"
                :key="card.title"
                :class="['rounded-xl border p-3.5 shadow-sm', card.shellClass]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="wm-text-primary text-sm font-medium">
                            {{ card.title }}
                        </p>
                        <p class="wm-text-secondary mt-1 text-[11px]">
                            {{ card.description }}
                        </p>
                    </div>
                    <div
                        :class="[
                            'flex size-8 shrink-0 items-center justify-center rounded-lg border',
                            card.iconWrapClass,
                        ]"
                    >
                        <component
                            :is="card.icon"
                            class="size-4"
                            :class="card.iconClass"
                        />
                    </div>
                </div>

                <div class="mt-3 flex items-end justify-between gap-3">
                    <p
                        :class="[
                            'text-3xl font-bold tracking-tight tabular-nums',
                            card.value > 0
                                ? card.valueClass
                                : 'text-slate-950 dark:text-slate-100',
                        ]"
                    >
                        {{ card.value }}
                    </p>
                </div>
                <p class="wm-text-secondary mt-1.5 text-[11px]">
                    {{ card.statusText }}
                </p>
                <div
                    class="mt-3 h-1.5 rounded-full bg-white/70 dark:bg-slate-950/70"
                >
                    <div
                        class="h-full rounded-full"
                        :class="card.barClass"
                        :style="{
                            width: `${Math.min(card.value > 0 ? 22 + card.value * 12 : 16, 100)}%`,
                        }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
