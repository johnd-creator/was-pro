<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    RotateCcw,
    ShieldCheck,
    ShieldX,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaDateTime,
    formatFabaMaterial,
    formatFabaMovementType,
    formatFabaStatus,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaAuditLog,
    FabaClosingSnapshot,
    FabaMovement,
    FabaMonthlyApproval,
    FabaMonthlyRecap,
} from '@/types/faba';

const props = defineProps<{
    approval: FabaMonthlyApproval;
    recap: FabaMonthlyRecap;
    snapshot?: FabaClosingSnapshot | null;
    vendorBreakdown: Array<{
        vendor_id: string | null;
        vendor_name: string;
        quantity: number;
    }>;
    internalDestinationBreakdown: Array<{
        internal_destination_id: string | null;
        internal_destination_name: string;
        quantity: number;
    }>;
    purposeBreakdown: Array<{
        purpose_id: string | null;
        purpose_name: string;
        quantity: number;
    }>;
    movements: FabaMovement[];
    auditLogs: FabaAuditLog[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Approval FABA',
        href: wasteManagementRoutes.faba.approvals.index.url(),
    },
    {
        title:
            props.approval.period_label ??
            `${props.approval.month}/${props.approval.year}`,
        href: wasteManagementRoutes.faba.approvals.review([
            props.approval.year,
            props.approval.month,
        ]).url,
    },
];

const approveForm = useForm({ approval_note: '' });
const rejectForm = useForm({ rejection_note: '' });
const reopenForm = useForm({ reopen_note: '' });

function approve(): void {
    approveForm.post(
        wasteManagementRoutes.faba.approvals.approve([
            props.approval.year,
            props.approval.month,
        ]).url,
    );
}

function reject(): void {
    rejectForm.post(
        wasteManagementRoutes.faba.approvals.reject([
            props.approval.year,
            props.approval.month,
        ]).url,
    );
}

function reopen(): void {
    reopenForm.post(
        wasteManagementRoutes.faba.approvals.reopen([
            props.approval.year,
            props.approval.month,
        ]).url,
    );
}

function submitPeriod(): void {
    router.post(wasteManagementRoutes.faba.approvals.submit.url(), {
        year: props.approval.year,
        month: props.approval.month,
    });
}

function statusTone(status: string): string {
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
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Review Approval FABA"
    >
        <Head title="Review Approval FABA" />
        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[360px]"
            />
            <div
                class="pointer-events-none absolute -top-10 left-1/3 -z-10 h-56 w-56 rounded-full bg-amber-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-24 right-0 -z-10 h-64 w-64 rounded-full bg-emerald-200/15 blur-3xl"
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
                                    Period Review
                                </p>
                                <Heading
                                    :title="`Review ${approval.period_label ?? `${approval.month}/${approval.year}`}`"
                                    description="Tinjau angka periode, warning aktif, ledger movement, dan jejak audit dalam satu meja review sebelum keputusan dibuat."
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <span
                                    :class="[
                                        'rounded-full border px-3 py-1.5 text-xs font-medium shadow-sm',
                                        statusTone(approval.status),
                                    ]"
                                >
                                    {{ formatFabaStatus(approval.status) }}
                                </span>
                                <div
                                    class="wm-chip px-3 py-1.5 text-xs font-medium"
                                >
                                    Status operasional:
                                    {{
                                        approval.operational_status ||
                                        approval.status
                                    }}
                                </div>
                                <div
                                    v-if="approval.can_submit"
                                    class="wm-chip-amber px-3 py-1.5 text-xs font-medium"
                                >
                                    Siap submit
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-4">
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Produksi
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ recap.total_production }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        ton
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Pemanfaatan
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ recap.total_utilization }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        ton
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-neutral"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Saldo Awal
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ recap.opening_balance }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        ton
                                    </p>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
                                    >
                                        Saldo Akhir
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                    >
                                        {{ recap.closing_balance }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        ton
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
                                    Quick Actions
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Navigasi terkait
                                </p>
                            </div>

                            <Button
                                variant="outline"
                                class="w-full justify-between border-white/90 bg-white/85 shadow-sm dark:bg-slate-950/80"
                                as-child
                            >
                                <Link
                                    :href="
                                        wasteManagementRoutes.faba.recaps.monthly(
                                            {
                                                query: {
                                                    year: approval.year,
                                                    month: approval.month,
                                                },
                                            },
                                        ).url
                                    "
                                >
                                    Lihat rekap periode
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </Button>

                            <Button
                                variant="outline"
                                class="w-full justify-between border-white/90 bg-white/85 shadow-sm dark:bg-slate-950/80"
                                as-child
                            >
                                <Link
                                    :href="
                                        wasteManagementRoutes.faba.approvals.history.url()
                                    "
                                >
                                    Buka histori approval
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </Button>

                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Warning
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        recap.warnings.length > 0
                                            ? `${recap.warnings.length} warning aktif perlu diperiksa sebelum keputusan final.`
                                            : 'Tidak ada warning aktif pada periode ini.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div
                    class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]"
                >
                    <section class="space-y-6">
                        <Card
                            class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <CardHeader>
                                <CardTitle>Ringkasan Operasional</CardTitle>
                            </CardHeader>
                            <CardContent class="grid gap-4 md:grid-cols-2">
                                <div
                                    class="rounded-[18px] border border-slate-200/80 bg-slate-50/85 p-4 dark:bg-slate-900/75"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Status Periode
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ formatFabaStatus(approval.status) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-[18px] border border-slate-200/80 bg-slate-50/85 p-4 dark:bg-slate-900/75"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Operasional
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            approval.operational_status ||
                                            approval.status
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="snapshot"
                                    class="rounded-[18px] border border-slate-200/80 bg-slate-50/85 p-4 dark:bg-slate-900/75"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Closing Snapshot
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{ snapshot.status }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Approved at
                                        {{
                                            formatFabaDateTime(
                                                snapshot.approved_at,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="snapshot"
                                    class="rounded-[18px] border border-slate-200/80 bg-slate-50/85 p-4 dark:bg-slate-900/75"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Approved By
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                    >
                                        {{
                                            snapshot.approved_by_user?.name ||
                                            '-'
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="approval.rejection_note"
                                    class="rounded-[18px] border border-rose-200/80 bg-rose-50/90 p-4 md:col-span-2"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-rose-700/80 uppercase"
                                    >
                                        Catatan Reject
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-rose-800"
                                    >
                                        {{ approval.rejection_note }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            v-if="recap.warnings.length > 0"
                            class="rounded-[28px] border-amber-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <CardHeader>
                                <CardTitle>Peringatan Periode</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="warning in recap.warnings"
                                    :key="warning.code"
                                    class="flex gap-3 rounded-[18px] border border-amber-200/70 bg-amber-50/80 px-4 py-3"
                                >
                                    <div
                                        class="rounded-2xl bg-white/80 p-2 text-amber-700 dark:bg-slate-950/75"
                                    >
                                        <AlertTriangle class="h-4 w-4" />
                                    </div>
                                    <p class="text-sm leading-6 text-amber-900">
                                        {{ warning.message }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <CardHeader>
                                <CardTitle>Movement Ledger</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p
                                    v-if="movements.length === 0"
                                    class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada movement ledger pada periode ini.
                                </p>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="item in movements"
                                        :key="item.id"
                                        class="grid gap-3 rounded-[20px] border border-slate-200/80 bg-slate-50/75 px-4 py-4 md:grid-cols-[140px_minmax(0,1fr)_auto] dark:border-slate-800/80"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Tanggal
                                            </p>
                                            <p
                                                class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    formatFabaDate(
                                                        item.transaction_date,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    item.display_number ||
                                                    item.id
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                            >
                                                {{
                                                    formatFabaMaterial(
                                                        item.material_type,
                                                    )
                                                }}
                                                •
                                                {{
                                                    formatFabaMovementType(
                                                        item.movement_type,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Qty
                                            </p>
                                            <p
                                                class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-100"
                                            >
                                                {{ item.stock_effect }}
                                                {{ item.quantity }}
                                                {{ item.unit }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </section>

                    <section class="space-y-6">
                        <Card
                            class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <CardHeader>
                                <CardTitle>Breakdown Pemanfaatan</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-5">
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Vendor
                                    </p>
                                    <div
                                        v-if="vendorBreakdown.length === 0"
                                        class="mt-3 rounded-[18px] border border-dashed border-slate-200 bg-slate-50/70 px-4 py-6 text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Belum ada pemanfaatan eksternal pada
                                        periode ini.
                                    </div>
                                    <div v-else class="mt-3 space-y-2">
                                        <div
                                            v-for="item in vendorBreakdown"
                                            :key="
                                                item.vendor_id ??
                                                item.vendor_name
                                            "
                                            class="flex items-center justify-between rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                        >
                                            <span
                                                class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >{{ item.vendor_name }}</span
                                            >
                                            <span
                                                class="text-sm text-slate-600 dark:text-slate-300"
                                                >{{ item.quantity }} ton</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tujuan Internal
                                    </p>
                                    <div
                                        v-if="
                                            internalDestinationBreakdown.length ===
                                            0
                                        "
                                        class="mt-3 rounded-[18px] border border-dashed border-slate-200 bg-slate-50/70 px-4 py-6 text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Belum ada pemanfaatan internal pada
                                        periode ini.
                                    </div>
                                    <div v-else class="mt-3 space-y-2">
                                        <div
                                            v-for="item in internalDestinationBreakdown"
                                            :key="
                                                item.internal_destination_id ??
                                                item.internal_destination_name
                                            "
                                            class="flex items-center justify-between rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                        >
                                            <span
                                                class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >{{
                                                    item.internal_destination_name
                                                }}</span
                                            >
                                            <span
                                                class="text-sm text-slate-600 dark:text-slate-300"
                                                >{{ item.quantity }} ton</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Purpose / Use-case
                                    </p>
                                    <div
                                        v-if="purposeBreakdown.length === 0"
                                        class="mt-3 rounded-[18px] border border-dashed border-slate-200 bg-slate-50/70 px-4 py-6 text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Belum ada purpose yang dicatat pada
                                        periode ini.
                                    </div>
                                    <div v-else class="mt-3 space-y-2">
                                        <div
                                            v-for="item in purposeBreakdown"
                                            :key="
                                                item.purpose_id ??
                                                item.purpose_name
                                            "
                                            class="flex items-center justify-between rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                        >
                                            <span
                                                class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >{{ item.purpose_name }}</span
                                            >
                                            <span
                                                class="text-sm text-slate-600 dark:text-slate-300"
                                                >{{ item.quantity }} ton</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <CardHeader>
                                <CardTitle>Panel Keputusan</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <Card
                                    v-if="approval.can_approve"
                                    class="rounded-[22px] border-emerald-200/80 bg-emerald-50/60 shadow-none"
                                >
                                    <CardHeader class="pb-3">
                                        <CardTitle
                                            class="flex items-center gap-2 text-base"
                                        >
                                            <ShieldCheck
                                                class="h-4 w-4 text-emerald-700"
                                            />
                                            Approve
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <Textarea
                                            v-model="approveForm.approval_note"
                                            placeholder="Catatan approve (opsional)"
                                        />
                                        <Button
                                            @click="approve"
                                            :disabled="approveForm.processing"
                                        >
                                            <CheckCircle2
                                                class="mr-2 h-4 w-4"
                                            />
                                            Approve periode
                                        </Button>
                                    </CardContent>
                                </Card>

                                <Card
                                    v-if="approval.can_reject"
                                    class="rounded-[22px] border-rose-200/80 bg-rose-50/60 shadow-none"
                                >
                                    <CardHeader class="pb-3">
                                        <CardTitle
                                            class="flex items-center gap-2 text-base"
                                        >
                                            <ShieldX
                                                class="h-4 w-4 text-rose-700"
                                            />
                                            Reject
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <Textarea
                                            v-model="rejectForm.rejection_note"
                                            placeholder="Catatan reject"
                                        />
                                        <Button
                                            variant="destructive"
                                            @click="reject"
                                            :disabled="rejectForm.processing"
                                        >
                                            Reject periode
                                        </Button>
                                    </CardContent>
                                </Card>

                                <Card
                                    v-if="approval.can_reopen"
                                    class="rounded-[22px] border-slate-200/80 bg-slate-50/70 shadow-none dark:bg-slate-900/65"
                                >
                                    <CardHeader class="pb-3">
                                        <CardTitle
                                            class="flex items-center gap-2 text-base"
                                        >
                                            <RotateCcw
                                                class="h-4 w-4 text-slate-700 dark:text-slate-200"
                                            />
                                            Reopen
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <Textarea
                                            v-model="reopenForm.reopen_note"
                                            placeholder="Alasan membuka kembali periode"
                                        />
                                        <Button
                                            variant="outline"
                                            @click="reopen"
                                            :disabled="reopenForm.processing"
                                        >
                                            Buka kembali periode
                                        </Button>
                                    </CardContent>
                                </Card>

                                <Button
                                    v-if="approval.can_submit"
                                    class="w-full justify-between"
                                    @click="submitPeriod"
                                >
                                    Submit periode
                                    <ArrowRight class="h-4 w-4" />
                                </Button>
                            </CardContent>
                        </Card>

                        <Card
                            class="rounded-[28px] border-slate-200/80 bg-white/90 shadow-[0_22px_45px_-32px_rgba(15,23,42,0.28)] dark:bg-slate-950/85"
                        >
                            <CardHeader>
                                <CardTitle>Approval Log</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p
                                    v-if="auditLogs.length === 0"
                                    class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Belum ada audit trail pada periode ini.
                                </p>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="log in auditLogs"
                                        :key="log.id"
                                        class="rounded-[18px] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:bg-slate-900/70"
                                    >
                                        <p
                                            class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ log.actor?.name || 'Sistem' }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                formatFabaDateTime(
                                                    log.created_at,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                        >
                                            {{ log.summary }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </div>
        </div>
    </WasteManagementLayout>
</template>
