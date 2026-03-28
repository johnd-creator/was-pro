<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
    props.approvals.filter((a) => a.type === 'waste_record'),
);

const fabaApprovals = computed(() =>
    props.approvals.filter((a) => a.type === 'faba_approval'),
);

const hasApprovals = computed(() =>
    wasteApprovals.value.length > 0 || fabaApprovals.value.length > 0,
);
</script>

<template>
    <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="border-b border-slate-100 pb-5">
            <div>
                <CardTitle class="text-lg">Antrian Tindakan</CardTitle>
                <CardDescription>
                    Prioritas review untuk catatan limbah dan approval
                    FABA yang menunggu keputusan.
                </CardDescription>
            </div>
        </CardHeader>
        <CardContent class="p-6">
            <div
                v-if="!hasApprovals"
                class="rounded-2xl border border-dashed border-border bg-muted/30 p-6 text-center"
            >
                <p class="font-medium text-foreground">
                    Tidak ada antrian approval
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Semua catatan telah diproses.
                </p>
            </div>

            <div v-else class="grid gap-6 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-slate-50/40 p-4">
                    <div
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h4 class="text-sm font-semibold text-foreground">
                                Catatan Limbah ({{ wasteApprovals.length }})
                            </h4>
                            <p class="text-xs text-muted-foreground">
                                Review catatan limbah yang menunggu approval.
                            </p>
                        </div>
                        <Button
                            v-if="wasteApprovals.length > 0"
                            size="sm"
                            variant="outline"
                            as-child
                        >
                            <Link :href="wasteManagementRoutes.records.pendingApproval().url">
                                Buka semua
                            </Link>
                        </Button>
                    </div>

                    <div
                        v-if="wasteApprovals.length === 0"
                        class="rounded-2xl border border-dashed border-border bg-background/70 p-5 text-center"
                    >
                        <p class="font-medium text-foreground">
                            Tidak ada antrian catatan limbah
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Semua catatan limbah sudah diproses.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <Link
                            v-for="item in wasteApprovals"
                            :key="item.id"
                            :href="wasteManagementRoutes.records.show(item.id).url"
                            class="group block rounded-2xl border border-slate-100 bg-background/90 p-4 transition-colors hover:bg-background"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <p class="font-semibold text-foreground">
                                        {{ item.record_number }}
                                    </p>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ item.waste_type }} •
                                        {{ item.category }}
                                    </p>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ item.quantity ?? 0 }} {{ item.unit }}
                                        • Oleh {{ item.submitted_by }}
                                    </p>
                                </div>
                                <Badge
                                    variant="secondary"
                                    class="shrink-0 rounded-full px-2.5 py-1"
                                >
                                    Pending
                                </Badge>
                            </div>
                        </Link>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50/40 p-4">
                    <div
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h4 class="text-sm font-semibold text-foreground">
                                FABA ({{ fabaApprovals.length }})
                            </h4>
                            <p class="text-xs text-muted-foreground">
                                Review approval FABA yang menunggu keputusan.
                            </p>
                        </div>
                        <Button
                            v-if="fabaApprovals.length > 0"
                            size="sm"
                            variant="outline"
                            as-child
                        >
                            <Link :href="wasteManagementRoutes.faba.approvals.index().url">
                                Buka semua
                            </Link>
                        </Button>
                    </div>

                    <div
                        v-if="fabaApprovals.length === 0"
                        class="rounded-2xl border border-dashed border-border bg-background/70 p-5 text-center"
                    >
                        <p class="font-medium text-foreground">
                            Tidak ada antrian FABA
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Semua approval FABA sudah diproses.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <Link
                            v-for="item in fabaApprovals"
                            :key="item.id"
                            :href="
                                wasteManagementRoutes.faba.approvals.review([
                                    item.year ?? 0,
                                    item.month ?? 0,
                                ]).url
                            "
                            class="group block rounded-2xl border border-slate-100 bg-background/90 p-4 transition-colors hover:bg-background"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <p class="font-semibold text-foreground">
                                        {{ item.period_label }}
                                    </p>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        Review periode bulanan FABA yang menunggu
                                        keputusan final.
                                    </p>
                                </div>
                                <Badge
                                    variant="secondary"
                                    class="shrink-0 rounded-full px-2.5 py-1"
                                >
                                    {{ formatFabaStatus(item.status) }}
                                </Badge>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
