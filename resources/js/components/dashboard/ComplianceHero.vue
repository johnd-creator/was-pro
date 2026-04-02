<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ShieldAlert,
    ShieldCheck,
    Timer,
    type LucideIcon,
} from 'lucide-vue-next';
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
}

interface HeroTone {
    shellClass: string;
    badgeClass: string;
    summaryClass: string;
    buttonClass: string;
    iconWrapClass: string;
    iconGlowClass: string;
}

interface MetricCard {
    title: string;
    value: number;
    description: string;
    icon: LucideIcon;
    cardClass: string;
    accentClass: string;
    iconClass: string;
}

const props = defineProps<Props>();

const hasAlerts = computed(
    () => props.riskStatus === 'critical' || props.expiredWaste > 0,
);

const primaryMessage = computed(() => {
    if (props.expiredWaste > 0) {
        return `${props.expiredWaste} catatan limbah sudah melewati batas simpan dan perlu penanganan segera.`;
    }

    if (props.fabaWarnings > 0) {
        return `${props.fabaWarnings} periode FABA memerlukan perhatian karena warning saldo atau konsistensi data.`;
    }

    if (props.pendingWasteApprovals + props.pendingFabaApprovals > 0) {
        return `Terdapat ${props.pendingWasteApprovals + props.pendingFabaApprovals} antrian approval yang menunggu keputusan untuk menjaga ritme operasional tetap aman.`;
    }

    if (props.expiringSoonWaste > 0) {
        return `${props.expiringSoonWaste} catatan limbah akan mendekati batas simpan dalam 7 hari ke depan.`;
    }

    return 'Kepatuhan operasional berada dalam kondisi terkendali dan tidak ada anomali utama yang perlu ditindaklanjuti sekarang.';
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

const summaryLabel = computed(() => {
    if (totalRiskCount.value === 0) {
        return 'Tidak ada isu aktif';
    }

    if (props.riskStatus === 'critical') {
        return 'Butuh tindakan cepat';
    }

    if (props.riskStatus === 'warning') {
        return 'Perlu tindak lanjut';
    }

    return 'Terkendali';
});

const summaryDetails = computed(() => {
    const parts: string[] = [];

    if (props.expiredWaste > 0) {
        parts.push(`${props.expiredWaste} kritis`);
    }

    if (props.expiringSoonWaste > 0) {
        parts.push(`${props.expiringSoonWaste} mendekati batas`);
    }

    if (totalApprovalCount.value > 0) {
        parts.push(`${totalApprovalCount.value} approval`);
    }

    if (props.fabaWarnings > 0) {
        parts.push(`${props.fabaWarnings} warning FABA`);
    }

    return parts.length > 0 ? parts.join(' • ') : 'Seluruh indikator aman';
});

const toneMap: Record<Props['riskTone'], HeroTone> = {
    red: {
        shellClass:
            'border border-red-300/40 bg-gradient-to-br from-red-700 via-red-600 to-rose-600',
        badgeClass: 'bg-white/16 text-white ring-1 ring-white/20',
        summaryClass: 'bg-white/14 ring-1 ring-white/16',
        buttonClass:
            'bg-white text-red-700 shadow-lg transition-all duration-200 hover:bg-red-50',
        iconWrapClass: 'bg-white/12 ring-1 ring-white/16',
        iconGlowClass: 'bg-red-300/20',
    },
    orange: {
        shellClass:
            'border border-amber-300/40 bg-gradient-to-br from-amber-600 via-orange-500 to-amber-500',
        badgeClass: 'bg-white/16 text-white ring-1 ring-white/20',
        summaryClass: 'bg-white/14 ring-1 ring-white/16',
        buttonClass:
            'bg-white text-amber-700 shadow-lg transition-all duration-200 hover:bg-amber-50',
        iconWrapClass: 'bg-white/12 ring-1 ring-white/16',
        iconGlowClass: 'bg-amber-200/20',
    },
    green: {
        shellClass:
            'border border-emerald-300/35 bg-gradient-to-br from-teal-700 via-emerald-600 to-cyan-500',
        badgeClass: 'bg-white/16 text-white ring-1 ring-white/20',
        summaryClass: 'bg-white/14 ring-1 ring-white/16',
        buttonClass:
            'bg-white text-emerald-700 shadow-lg transition-all duration-200 hover:bg-emerald-50',
        iconWrapClass: 'bg-white/12 ring-1 ring-white/16',
        iconGlowClass: 'bg-emerald-200/20',
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
        cardClass:
            props.expiredWaste > 0
                ? 'border-red-200/45 bg-red-950/20 ring-1 ring-red-200/30'
                : 'border-white/16 bg-white/8',
        accentClass: props.expiredWaste > 0 ? 'bg-red-200/28' : 'bg-white/12',
        iconClass: props.expiredWaste > 0 ? 'text-red-100' : 'text-white/88',
    },
    {
        title: 'Mendekati Batas Simpan',
        value: props.expiringSoonWaste,
        description: 'Dalam 7 hari',
        icon: Timer,
        cardClass:
            props.expiringSoonWaste > 0
                ? 'border-amber-200/45 bg-amber-950/18 ring-1 ring-amber-200/25'
                : 'border-white/16 bg-white/8',
        accentClass:
            props.expiringSoonWaste > 0 ? 'bg-amber-200/28' : 'bg-white/12',
        iconClass:
            props.expiringSoonWaste > 0 ? 'text-amber-100' : 'text-white/88',
    },
    {
        title: 'Pending Approval',
        value: totalApprovalCount.value,
        description: `${props.pendingWasteApprovals} limbah, ${props.pendingFabaApprovals} FABA`,
        icon: AlertTriangle,
        cardClass:
            totalApprovalCount.value > 0
                ? 'border-sky-200/45 bg-sky-950/18 ring-1 ring-sky-200/25'
                : 'border-white/16 bg-white/8',
        accentClass:
            totalApprovalCount.value > 0 ? 'bg-sky-200/28' : 'bg-white/12',
        iconClass:
            totalApprovalCount.value > 0 ? 'text-sky-100' : 'text-white/88',
    },
    {
        title: 'Peringatan FABA',
        value: props.fabaWarnings,
        description: 'Periode bermasalah',
        icon: AlertTriangle,
        cardClass:
            props.fabaWarnings > 0
                ? 'border-rose-200/45 bg-rose-950/18 ring-1 ring-rose-200/25'
                : 'border-white/16 bg-white/8',
        accentClass: props.fabaWarnings > 0 ? 'bg-rose-200/28' : 'bg-white/12',
        iconClass: props.fabaWarnings > 0 ? 'text-rose-100' : 'text-white/88',
    },
]);
</script>

<template>
    <div
        :class="[
            'overflow-hidden rounded-[28px] text-white shadow-xl transition-all duration-300',
            tone.shellClass,
        ]"
    >
        <div class="flex flex-col gap-5 p-5 lg:p-6">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_260px] lg:items-stretch xl:grid-cols-[minmax(0,1fr)_300px]">
                <div class="space-y-5">
                    <div
                        :class="[
                            'inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.12em] uppercase backdrop-blur-sm',
                            tone.badgeClass,
                        ]"
                    >
                        <component :is="statusIcon" class="size-3.5" />
                        Status Kepatuhan: {{ riskLabel }}
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-xl font-bold tracking-tight lg:text-[2rem]">
                            Tinjau Risiko Kepatuhan
                        </h2>
                        <p class="max-w-2xl text-sm leading-6 text-white/82 lg:text-[15px]">
                            {{ primaryMessage }}
                        </p>
                    </div>

                    <div
                        :class="[
                            'inline-flex flex-col gap-1 rounded-2xl px-4 py-3 text-left backdrop-blur-sm',
                            tone.summaryClass,
                        ]"
                    >
                        <p class="text-[11px] font-semibold tracking-[0.14em] uppercase text-white/75">
                            {{ summaryLabel }}
                        </p>
                        <p class="text-2xl font-bold tracking-tight">
                            {{ totalRiskCount }}
                        </p>
                        <p class="text-xs text-white/72">
                            {{ summaryDetails }}
                        </p>
                    </div>
                </div>

                <div class="hidden lg:flex lg:min-h-full lg:items-end lg:justify-end">
                    <div
                        :class="[
                            'relative flex size-36 items-center justify-center overflow-hidden rounded-[32px] backdrop-blur-sm xl:size-40',
                            tone.iconWrapClass,
                        ]"
                    >
                        <div
                            :class="[
                                'absolute inset-4 rounded-full blur-2xl',
                                tone.iconGlowClass,
                            ]"
                        />
                        <component :is="statusIcon" class="relative z-10 size-16 text-white/92 xl:size-20" />
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:max-w-[70%] xl:max-w-[72%] xl:grid-cols-4">
                <div
                    v-for="card in metricCards"
                    :key="card.title"
                    :class="[
                        'flex min-h-[108px] items-start justify-between gap-3 rounded-2xl border px-3.5 py-3.5 backdrop-blur-sm transition-all duration-200 hover:bg-white/14',
                        card.cardClass,
                    ]"
                >
                    <div class="min-w-0 space-y-2">
                        <p class="text-[13px] font-semibold leading-5 text-white/90">
                            {{ card.title }}
                        </p>
                        <p class="text-[2rem] leading-none font-bold tracking-tight">
                            {{ card.value }}
                        </p>
                        <p class="text-[11px] leading-4 text-white/72">
                            {{ card.description }}
                        </p>
                    </div>

                    <div
                        :class="[
                            'flex size-11 shrink-0 items-center justify-center rounded-2xl',
                            card.accentClass,
                        ]"
                    >
                        <component :is="card.icon" :class="['size-5', card.iconClass]" />
                    </div>
                </div>
            </div>

            <div>
                <Button as-child :class="tone.buttonClass">
                    <Link
                        :href="
                            hasAlerts
                                ? wasteManagementRoutes.records.index().url
                                : wasteManagementRoutes.records.pendingApproval().url
                        "
                    >
                        {{ hasAlerts ? 'Tinjau Catatan Kritis' : 'Lihat Antrian Approval' }}
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
