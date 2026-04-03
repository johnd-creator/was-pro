<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, Clock3, ShieldCheck, Workflow } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaDateTime, formatFabaStatus } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type { FabaAuditLog, FabaMonthlyApproval } from '@/types/faba';

const props = defineProps<{
    approvals: FabaMonthlyApproval[];
    auditLogs: FabaAuditLog[];
}>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Histori Approval FABA',
        href: wasteManagementRoutes.faba.approvals.history.url(),
    },
];

const approvedCount = computed(
    () =>
        props.approvals.filter((approval) => approval.status === 'approved')
            .length,
);

const rejectedCount = computed(
    () =>
        props.approvals.filter((approval) => approval.status === 'rejected')
            .length,
);

const latestAuditCount = computed(() => props.auditLogs.length);
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Histori Approval FABA"
    >
        <Head title="Histori Approval FABA" />
        <div
            class="relative overflow-x-hidden px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <div
                class="wm-page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-[340px]"
            />
            <div
                class="pointer-events-none absolute -top-8 left-1/3 -z-10 h-56 w-56 rounded-full bg-blue-200/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-20 right-0 -z-10 h-56 w-56 rounded-full bg-emerald-200/15 blur-3xl"
            />

            <div class="space-y-8">
                <section
                    class="wm-surface-hero overflow-hidden rounded-[30px] via-slate-50/80 to-blue-50/20 dark:via-slate-900 dark:to-blue-950/20"
                >
                    <div
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-6"
                    >
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-blue-700/70 uppercase"
                                >
                                    Approval Archive
                                </p>
                                <Heading
                                    title="Histori Approval FABA"
                                    description="Pantau keputusan periode, reviewer yang terlibat, dan audit trail perubahan penting dalam satu meja arsip yang lebih mudah discan."
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
                                                Total Periode
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ approvals.length }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-neutral"
                                        >
                                            <Workflow class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="wm-hero-stat-card wm-hero-stat-emerald"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.14em] text-emerald-700/80 uppercase"
                                            >
                                                Disetujui
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ approvedCount }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-emerald"
                                        >
                                            <ShieldCheck class="h-5 w-5" />
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
                                                Audit Terbaru
                                            </p>
                                            <p
                                                class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                            >
                                                {{ latestAuditCount }}
                                            </p>
                                        </div>
                                        <div
                                            class="wm-hero-icon wm-hero-icon-amber"
                                        >
                                            <Clock3 class="h-5 w-5" />
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
                                    Ringkasan Cepat
                                </p>
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                >
                                    Status keputusan
                                </p>
                            </div>

                            <div class="wm-surface-subtle rounded-[22px] p-4">
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{ rejectedCount }} periode tercatat sebagai
                                    reject dan memerlukan pelacakan ulang bila
                                    perlu dibuka kembali.
                                </p>
                            </div>

                            <div
                                class="rounded-[22px] border border-white/90 bg-white/90 px-4 py-4 shadow-sm shadow-slate-100/60 dark:bg-slate-950/85"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="rounded-2xl bg-blue-100 p-3 text-blue-700"
                                    >
                                        <Activity class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            Audit log aktif
                                        </p>
                                        <p
                                            class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                                        >
                                            {{ auditLogs.length }} entri
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <p
                            class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            Arsip Approval
                        </p>
                        <h2
                            class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                        >
                            Timeline Periode
                        </h2>
                    </div>

                    <div
                        v-if="approvals.length === 0"
                        class="rounded-[26px] border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center text-sm text-slate-500 shadow-sm dark:text-slate-400"
                    >
                        Belum ada histori approval FABA.
                    </div>

                    <div
                        v-else
                        class="wm-surface-elevated overflow-hidden rounded-[28px]"
                    >
                        <Table>
                            <TableHeader
                                class="bg-slate-50/90 dark:bg-slate-900/80"
                            >
                                <TableRow
                                    class="border-slate-200/80 dark:border-slate-800/80"
                                >
                                    <TableHead>Periode</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead>Approved</TableHead>
                                    <TableHead>Rejected Note</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="approval in approvals"
                                    :key="
                                        approval.id ??
                                        `${approval.year}-${approval.month}`
                                    "
                                    class="border-slate-200/70 dark:border-slate-800/70"
                                >
                                    <TableCell
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            approval.period_label ||
                                            `${approval.month}/${approval.year}`
                                        }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full border border-slate-200/80 bg-white/90 text-slate-700 dark:text-slate-200"
                                        >
                                            {{
                                                formatFabaStatus(
                                                    approval.status,
                                                )
                                            }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{{
                                        approval.submitted_by_user?.name || '-'
                                    }}</TableCell>
                                    <TableCell>{{
                                        approval.approved_by_user?.name || '-'
                                    }}</TableCell>
                                    <TableCell
                                        class="max-w-sm text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{ approval.rejection_note || '-' }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <p
                            class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            Audit Trail
                        </p>
                        <h2
                            class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100"
                        >
                            Jejak Perubahan Terbaru
                        </h2>
                    </div>

                    <div
                        v-if="auditLogs.length === 0"
                        class="rounded-[26px] border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center text-sm text-slate-500 shadow-sm dark:text-slate-400"
                    >
                        Belum ada audit trail FABA.
                    </div>

                    <div
                        v-else
                        class="wm-surface-elevated overflow-hidden rounded-[28px]"
                    >
                        <Table>
                            <TableHeader
                                class="bg-slate-50/90 dark:bg-slate-900/80"
                            >
                                <TableRow
                                    class="border-slate-200/80 dark:border-slate-800/80"
                                >
                                    <TableHead>Waktu</TableHead>
                                    <TableHead>Modul</TableHead>
                                    <TableHead>Aksi</TableHead>
                                    <TableHead>User</TableHead>
                                    <TableHead>Ringkasan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="log in auditLogs"
                                    :key="log.id"
                                    class="border-slate-200/70 dark:border-slate-800/70"
                                >
                                    <TableCell>{{
                                        formatFabaDateTime(log.created_at)
                                    }}</TableCell>
                                    <TableCell>{{ log.module }}</TableCell>
                                    <TableCell>{{ log.action }}</TableCell>
                                    <TableCell>{{
                                        log.actor?.name || '-'
                                    }}</TableCell>
                                    <TableCell
                                        class="max-w-md text-sm text-slate-600 dark:text-slate-300"
                                        >{{ log.summary }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </div>
    </WasteManagementLayout>
</template>
