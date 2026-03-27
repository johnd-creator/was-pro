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

const hasApprovals = computed(() => props.approvals.length > 0);

const wasteApprovals = computed(() =>
    props.approvals.filter((a) => a.type === 'waste_record'),
);

const fabaApprovals = computed(() =>
    props.approvals.filter((a) => a.type === 'faba_approval'),
);
</script>

<template>
    <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="border-b border-slate-100 pb-5">
            <div
                class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <CardTitle class="text-lg">Antrian Tindakan</CardTitle>
                    <CardDescription>
                        Prioritas review untuk catatan limbah dan approval
                        FABA yang menunggu keputusan.
                    </CardDescription>
                </div>
                <Button
                    v-if="hasApprovals"
                    size="default"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            wasteApprovals.length > 0
                                ? wasteManagementRoutes.records.pendingApproval()
                                      .url
                                : wasteManagementRoutes.faba.approvals.index()
                                      .url
                        "
                    >
                        Buka semua
                    </Link>
                </Button>
            </div>
        </CardHeader>
        <CardContent class="p-6">
            <div v-if="!hasApprovals" class="rounded-2xl border border-dashed border-border bg-muted/30 p-6 text-center">
                <p class="font-medium text-foreground">
                    Tidak ada antrian approval
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Semua catatan telah diproses.
                </p>
            </div>

            <div v-else class="space-y-6">
                <!-- Waste Approvals Section -->
                <div v-if="wasteApprovals.length > 0">
                    <h4 class="mb-3 text-sm font-semibold text-foreground">
                        Catatan Limbah ({{ wasteApprovals.length }})
                    </h4>
                    <div class="space-y-3">
                        <Link
                            v-for="item in wasteApprovals"
                            :key="item.id"
                            :href="
                                wasteManagementRoutes.records.show(
                                    item.id,
                                ).url
                            "
                            class="group block rounded-2xl border border-slate-100 bg-slate-50/60 p-4 transition-colors hover:bg-slate-50"
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
                                        {{ item.quantity ?? 0 }} {{ item.unit }} •
                                        Oleh {{ item.submitted_by }}
                                    </p>
                                </div>
                                <Badge variant="secondary" class="shrink-0 rounded-full px-2.5 py-1">
                                    Pending
                                </Badge>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- FABA Approvals Section -->
                <div v-if="fabaApprovals.length > 0">
                    <h4 class="mb-3 text-sm font-semibold text-foreground">
                        FABA ({{ fabaApprovals.length }})
                    </h4>
                    <div class="space-y-3">
                        <Link
                            v-for="item in fabaApprovals"
                            :key="item.id"
                            :href="
                                wasteManagementRoutes.faba.approvals.review([
                                    item.year ?? 0,
                                    item.month ?? 0,
                                ]).url
                            "
                            class="group block rounded-2xl border border-slate-100 bg-slate-50/60 p-4 transition-colors hover:bg-slate-50"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <p class="font-semibold text-foreground">
                                        {{ item.period_label }}
                                    </p>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        Review periode bulanan FABA yang menunggu keputusan final.
                                    </p>
                                </div>
                                <Badge variant="secondary" class="shrink-0 rounded-full px-2.5 py-1">
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
