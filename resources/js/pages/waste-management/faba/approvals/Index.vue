<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    { title: 'Approval FABA', href: wasteManagementRoutes.faba.approvals.index.url() },
];

const filterForm = reactive({
    year: props.year,
});

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.approvals.index(), filterForm);
}

function submitPeriod(year: number, month: number): void {
    router.post(wasteManagementRoutes.faba.approvals.submit.url(), { year, month });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Approval Bulanan FABA"
    >
        <Head title="Approval Bulanan FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Approval Bulanan FABA"
                description="Review periode yang terbentuk otomatis dari transaksi."
            />
            <Card>
                <CardHeader><CardTitle>Filter tahun</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2">
                        <Label>Tahun</Label>
                        <Input v-model="filterForm.year" type="number" />
                    </div>
                    <div class="flex items-end">
                        <Button @click="applyFilters">Terapkan</Button>
                    </div>
                </CardContent>
            </Card>
            <div
                v-if="periods.length === 0"
                class="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada periode transaksi FABA pada tahun ini.
            </div>
            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="item in periods" :key="`${item.year}-${item.month}`">
                    <CardHeader>
                        <CardTitle>{{ item.period_label }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p>Total produksi: {{ item.recap.total_production }} ton</p>
                        <p>Total pemanfaatan: {{ item.recap.total_utilization }} ton</p>
                        <p>Saldo akhir: {{ item.recap.closing_balance }} ton</p>
                        <p>Status: {{ formatFabaStatus(item.status) }}</p>
                        <p
                            v-if="item.recap.warnings.length > 0"
                            class="text-sm text-amber-600"
                        >
                            {{ item.recap.warnings.length }} warning perlu ditinjau.
                        </p>
                        <div class="flex flex-wrap gap-2 pt-2">
                            <Link :href="wasteManagementRoutes.faba.approvals.review([item.year, item.month]).url">
                                Review periode
                            </Link>
                            <Button
                                v-if="item.can_submit"
                                size="sm"
                                @click="submitPeriod(item.year, item.month)"
                            >
                                Submit
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
