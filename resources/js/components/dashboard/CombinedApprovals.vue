<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Clock3, FileText, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatFabaStatus } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';

interface ApprovalItem {
    id: string;
    record_number?: string;
    waste_type?: string;
    category?: string;
    quantity?: number;
    unit?: string;
    submitted_by?: string;
    submitted_at?: string;
    year?: number;
    month?: number;
    period_label?: string;
    status: string;
    type: 'waste_record' | 'faba_approval';
}

interface Props {
    approvals: ApprovalItem[];
}

const props = defineProps<Props>();

const wasteApprovals = computed(() =>
    props.approvals.filter((approval) => approval.type === 'waste_record'),
);

const fabaApprovals = computed(() =>
    props.approvals.filter((approval) => approval.type === 'faba_approval'),
);

const hasApprovals = computed(
    () => wasteApprovals.value.length > 0 || fabaApprovals.value.length > 0,
);

function approvalAgeHours(date?: string): number {
    if (!date) {
        return 0;
    }

    const submittedAt = new Date(date);

    if (Number.isNaN(submittedAt.getTime())) {
        return 0;
    }

    return Math.max(
        0,
        Math.floor((Date.now() - submittedAt.getTime()) / (1000 * 60 * 60)),
    );
}

const urgentWasteApprovals = computed(
    () =>
        wasteApprovals.value.filter(
            (approval) => approvalAgeHours(approval.submitted_at) >= 48,
        ).length,
);

const urgentFabaApprovals = computed(
    () =>
        fabaApprovals.value.filter(
            (approval) => approvalAgeHours(approval.submitted_at) >= 48,
        ).length,
);

function formatAgeLabel(date?: string): string {
    const diffHours = approvalAgeHours(date);

    if (diffHours === 0 && !date) {
        return 'Menunggu keputusan';
    }

    if (diffHours < 24) {
        return `${diffHours} jam`;
    }

    const diffDays = Math.floor(diffHours / 24);

    return `${diffDays} hari`;
}

function urgencyBadgeClass(date?: string): string {
    const diffHours = approvalAgeHours(date);

    if (diffHours >= 72) {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200';
    }

    if (diffHours >= 48) {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300';
}

function reviewButtonClass(date?: string): string {
    const diffHours = approvalAgeHours(date);

    if (diffHours >= 72) {
        return 'border-red-200 bg-red-600 text-white hover:bg-red-700 dark:border-red-900/60 dark:bg-red-700 dark:hover:bg-red-600';
    }

    if (diffHours >= 48) {
        return 'border-amber-200 bg-amber-600 text-white hover:bg-amber-700 dark:border-amber-900/60 dark:bg-amber-700 dark:hover:bg-amber-600';
    }

    return 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900';
}
</script>

<template>
    <Card class="wm-panel-work border shadow-sm">
        <CardHeader
            class="wm-border-strong border-b bg-slate-50/40 pb-4 dark:bg-slate-900/30"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="space-y-1.5">
                    <CardTitle class="wm-text-primary text-lg">
                        Work Queue Approval
                    </CardTitle>
                    <CardDescription
                        class="wm-text-secondary text-sm leading-6"
                    >
                        Putuskan antrian yang memengaruhi ritme operasional,
                        backlog limbah, dan review periode FABA.
                    </CardDescription>
                </div>

                <div class="grid min-w-[280px] gap-3 sm:grid-cols-3">
                    <div
                        class="wm-panel-elevated rounded-xl border px-3 py-2.5"
                    >
                        <p
                            class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase"
                        >
                            Total Queue
                        </p>
                        <p
                            class="wm-text-primary mt-1 text-2xl font-bold tracking-tight"
                        >
                            {{ approvals.length }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-2.5 dark:border-amber-900/60 dark:bg-amber-950/35"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-[0.12em] text-amber-700 uppercase dark:text-amber-200"
                        >
                            Perlu Follow-up
                        </p>
                        <p
                            class="mt-1 text-2xl font-bold tracking-tight text-amber-800 dark:text-amber-100"
                        >
                            {{ urgentWasteApprovals + urgentFabaApprovals }}
                        </p>
                    </div>
                    <div class="wm-panel rounded-xl border px-3 py-2.5">
                        <p
                            class="wm-text-muted text-[11px] font-semibold tracking-[0.12em] uppercase"
                        >
                            Jalur Review
                        </p>
                        <p class="wm-text-primary mt-1 text-sm font-semibold">
                            Limbah & FABA
                        </p>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="p-4">
            <div
                v-if="!hasApprovals"
                class="wm-panel rounded-xl border border-dashed p-6 text-center"
            >
                <p class="font-medium text-foreground">
                    Tidak ada antrian approval aktif
                </p>
                <p class="wm-text-secondary mt-1 text-sm">
                    Semua catatan yang membutuhkan keputusan sudah
                    terselesaikan.
                </p>
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-2">
                <div class="wm-panel rounded-xl border p-4">
                    <div
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <h4 class="text-sm font-semibold text-foreground">
                                Catatan Limbah Butuh Review
                            </h4>
                            <p class="wm-text-secondary text-xs">
                                {{ wasteApprovals.length }} item menunggu
                                approval supervisor.
                                <span v-if="urgentWasteApprovals > 0">
                                    {{ urgentWasteApprovals }} sudah melewati
                                    SLA internal.
                                </span>
                            </p>
                        </div>
                        <Button
                            v-if="wasteApprovals.length > 0"
                            size="sm"
                            variant="outline"
                            as-child
                            class="wm-panel-elevated h-8 rounded-md border"
                        >
                            <Link
                                :href="
                                    wasteManagementRoutes.records.pendingApproval()
                                        .url
                                "
                            >
                                Buka semua
                            </Link>
                        </Button>
                    </div>

                    <div
                        v-if="wasteApprovals.length === 0"
                        class="wm-panel-elevated rounded-lg border border-dashed p-5 text-center"
                    >
                        <p class="font-medium text-foreground">
                            Tidak ada antrian catatan limbah
                        </p>
                        <p class="wm-text-secondary mt-1 text-sm">
                            Semua catatan limbah sudah diproses.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="item in wasteApprovals"
                            :key="item.id"
                            class="wm-panel-elevated rounded-xl border p-3.5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <FileText
                                            class="wm-text-muted size-4"
                                        />
                                        <p
                                            class="font-semibold text-foreground"
                                        >
                                            {{ item.record_number }}
                                        </p>
                                    </div>
                                    <p class="wm-text-secondary mt-2 text-sm">
                                        {{ item.waste_type }} •
                                        {{ item.category }}
                                    </p>
                                    <div
                                        class="wm-text-secondary mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs"
                                    >
                                        <span
                                            >{{ item.quantity ?? 0 }}
                                            {{ item.unit }}</span
                                        >
                                        <span
                                            >Oleh {{ item.submitted_by }}</span
                                        >
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full border px-2 py-1 font-medium',
                                                urgencyBadgeClass(
                                                    item.submitted_at,
                                                ),
                                            ]"
                                        >
                                            <Clock3 class="size-3.5" />
                                            Umur
                                            {{
                                                formatAgeLabel(
                                                    item.submitted_at,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-2"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
                                    >
                                        Pending
                                    </Badge>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        as-child
                                        class="h-8 rounded-md"
                                        :class="
                                            reviewButtonClass(item.submitted_at)
                                        "
                                    >
                                        <Link
                                            :href="
                                                wasteManagementRoutes.records.show(
                                                    item.id,
                                                ).url
                                            "
                                        >
                                            Review
                                            <ArrowRight class="ml-1 size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wm-panel rounded-xl border p-4">
                    <div
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <h4 class="text-sm font-semibold text-foreground">
                                Approval FABA Menunggu Keputusan
                            </h4>
                            <p class="wm-text-secondary text-xs">
                                {{ fabaApprovals.length }} periode FABA
                                membutuhkan keputusan final.
                                <span v-if="urgentFabaApprovals > 0">
                                    {{ urgentFabaApprovals }} perlu perhatian
                                    cepat.
                                </span>
                            </p>
                        </div>
                        <Button
                            v-if="fabaApprovals.length > 0"
                            size="sm"
                            variant="outline"
                            as-child
                            class="wm-panel-elevated h-8 rounded-md border"
                        >
                            <Link
                                :href="
                                    wasteManagementRoutes.faba.approvals.index()
                                        .url
                                "
                            >
                                Buka semua
                            </Link>
                        </Button>
                    </div>

                    <div
                        v-if="fabaApprovals.length === 0"
                        class="wm-panel-elevated rounded-lg border border-dashed p-5 text-center"
                    >
                        <p class="font-medium text-foreground">
                            Tidak ada antrian FABA
                        </p>
                        <p class="wm-text-secondary mt-1 text-sm">
                            Semua approval FABA sudah diproses.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="item in fabaApprovals"
                            :key="item.id"
                            class="wm-panel-elevated rounded-xl border p-3.5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <ShieldAlert
                                            class="wm-text-muted size-4"
                                        />
                                        <p
                                            class="font-semibold text-foreground"
                                        >
                                            {{ item.period_label }}
                                        </p>
                                    </div>
                                    <div
                                        class="wm-text-secondary mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs"
                                    >
                                        <span
                                            >Status
                                            {{
                                                formatFabaStatus(item.status)
                                            }}</span
                                        >
                                        <span>Ritme review bulanan</span>
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full border px-2 py-1 font-medium',
                                                urgencyBadgeClass(
                                                    item.submitted_at,
                                                ),
                                            ]"
                                        >
                                            <Clock3 class="size-3.5" />
                                            Umur
                                            {{
                                                formatAgeLabel(
                                                    item.submitted_at,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-2"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="rounded-md border border-sky-200 bg-sky-50 px-2 py-1 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-200"
                                    >
                                        {{ formatFabaStatus(item.status) }}
                                    </Badge>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        as-child
                                        class="h-8 rounded-md"
                                        :class="
                                            reviewButtonClass(item.submitted_at)
                                        "
                                    >
                                        <Link
                                            :href="
                                                wasteManagementRoutes.faba.approvals.review(
                                                    [
                                                        item.year ?? 0,
                                                        item.month ?? 0,
                                                    ],
                                                ).url
                                            "
                                        >
                                            Review
                                            <ArrowRight class="ml-1 size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
