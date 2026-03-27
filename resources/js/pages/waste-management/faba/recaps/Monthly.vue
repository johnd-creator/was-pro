<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import {
    formatFabaDate,
    formatFabaDateTime,
    formatFabaMaterial,
    formatFabaMovementType,
} from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';
import type {
    FabaAuditLog,
    FabaClosingSnapshot,
    FabaMovement,
    FabaMonthlyRecap,
} from '@/types/faba';

const props = defineProps<{
    detail: {
        recap: FabaMonthlyRecap;
        snapshot?: FabaClosingSnapshot | null;
        movements: FabaMovement[];
        opening_balances: Array<{ material_type: string; quantity: number }>;
        vendor_breakdown: Array<{ vendor_id: string | null; vendor_name: string; quantity: number }>;
        internal_destination_breakdown: Array<{ internal_destination_id: string | null; internal_destination_name: string; quantity: number }>;
        purpose_breakdown: Array<{ purpose_id: string | null; purpose_name: string; quantity: number }>;
        audit_logs: FabaAuditLog[];
    };
    availablePeriods: Array<{ year: number; month: number; period_label: string }>;
    resolvedFromLatestPeriod: boolean;
    filters: { year: number; month: number };
}>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Rekap Bulanan FABA',
        href: wasteManagementRoutes.faba.recaps.monthly.url(),
    },
];

const form = reactive({
    selectedPeriod: `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`,
});

const hasEntries = computed(() => {
    return props.detail.movements.length > 0;
});

function applyFilters(): void {
    const [year, month] = form.selectedPeriod.split('-').map(Number);

    router.get(wasteManagementRoutes.faba.recaps.monthly(), { year, month });
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Rekap Bulanan FABA"
    >
        <Head title="Rekap Bulanan FABA" />
        <div class="space-y-6 p-6">
            <Heading
                :title="`Rekap ${detail.recap.period_label}`"
                description="Lihat total produksi, pemanfaatan, dan saldo per bulan."
            />
            <Alert v-if="resolvedFromLatestPeriod && availablePeriods.length > 0">
                <AlertTitle>Periode default disesuaikan</AlertTitle>
                <AlertDescription>
                    Halaman otomatis menampilkan periode terakhir yang memiliki transaksi FABA, yaitu {{ detail.recap.period_label }}.
                </AlertDescription>
            </Alert>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label>Periode</Label>
                    <Select v-model="form.selectedPeriod">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih periode rekap" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="period in availablePeriods"
                                :key="`${period.year}-${period.month}`"
                                :value="`${period.year}-${String(period.month).padStart(2, '0')}`"
                            >
                                {{ period.period_label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-end">
                    <Button @click="applyFilters">Terapkan</Button>
                </div>
            </div>
            <Alert
                v-if="detail.recap.warnings.length > 0"
                variant="destructive"
            >
                <AlertTitle>Peringatan rekap</AlertTitle>
                <AlertDescription>
                    <ul class="space-y-1">
                        <li v-for="warning in detail.recap.warnings" :key="warning.code">
                            {{ warning.message }}
                        </li>
                    </ul>
                </AlertDescription>
            </Alert>
            <div class="grid gap-4 md:grid-cols-4">
                <Card
                    ><CardHeader><CardTitle>Produksi FA</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.production_fly_ash
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader><CardTitle>Produksi BA</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.production_bottom_ash
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Pemanfaatan FA</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.utilization_fly_ash
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Pemanfaatan BA</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.utilization_bottom_ash
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Total Produksi</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.total_production
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Total Pemanfaatan</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.total_utilization
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader><CardTitle>Saldo Awal</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.opening_balance
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader><CardTitle>Saldo Akhir</CardTitle></CardHeader
                    ><CardContent>{{
                        detail.recap.closing_balance
                    }}</CardContent></Card
                >
            </div>
            <Card v-if="!hasEntries">
                <CardHeader>
                    <CardTitle>Periode Belum Memiliki Movement</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Belum ada movement FABA pada periode {{ detail.recap.period_label }}.
                </CardContent>
            </Card>
            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Opening Balance</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div v-for="item in detail.opening_balances" :key="item.material_type">
                            {{ formatFabaMaterial(item.material_type) }}: {{ item.quantity }} ton
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Ringkasan Ledger</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div>Inflow Fly Ash: {{ detail.recap.movement_summary?.inflow_fly_ash ?? 0 }} ton</div>
                        <div>Outflow Fly Ash: {{ detail.recap.movement_summary?.outflow_fly_ash ?? 0 }} ton</div>
                        <div>Inflow Bottom Ash: {{ detail.recap.movement_summary?.inflow_bottom_ash ?? 0 }} ton</div>
                        <div>Outflow Bottom Ash: {{ detail.recap.movement_summary?.outflow_bottom_ash ?? 0 }} ton</div>
                    </CardContent>
                </Card>
                <Card class="md:col-span-2">
                    <CardHeader><CardTitle>Stock Ledger</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="detail.movements.length === 0" class="text-muted-foreground">
                            Belum ada movement ledger pada periode ini.
                        </p>
                        <div v-for="item in detail.movements" :key="item.id">
                            {{ formatFabaDate(item.transaction_date) }} - {{ item.display_number || item.id }} - {{ formatFabaMaterial(item.material_type) }} - {{ formatFabaMovementType(item.movement_type) }} - {{ item.stock_effect }} {{ item.quantity }} {{ item.unit }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Vendor Breakdown</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="detail.vendor_breakdown.length === 0" class="text-muted-foreground">
                            Belum ada pemanfaatan eksternal pada periode ini.
                        </p>
                        <div v-for="item in detail.vendor_breakdown" :key="item.vendor_id ?? item.vendor_name">
                            {{ item.vendor_name }}: {{ item.quantity }} ton
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Breakdown Internal</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="detail.internal_destination_breakdown.length === 0" class="text-muted-foreground">
                            Belum ada pemanfaatan internal pada periode ini.
                        </p>
                        <div
                            v-for="item in detail.internal_destination_breakdown"
                            :key="item.internal_destination_id ?? item.internal_destination_name"
                        >
                            {{ item.internal_destination_name }}: {{ item.quantity }} ton
                        </div>
                    </CardContent>
                </Card>
                <Card class="md:col-span-2">
                    <CardHeader><CardTitle>Purpose / Use-case</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="detail.purpose_breakdown.length === 0" class="text-muted-foreground">
                            Belum ada purpose yang dicatat pada periode ini.
                        </p>
                        <div v-for="item in detail.purpose_breakdown" :key="item.purpose_id ?? item.purpose_name">
                            {{ item.purpose_name }}: {{ item.quantity }} ton
                        </div>
                    </CardContent>
                </Card>
                <Card v-if="detail.snapshot" class="md:col-span-2">
                    <CardHeader><CardTitle>Closing Snapshot</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div>Status: {{ detail.snapshot.status }}</div>
                        <div>Disetujui pada: {{ formatFabaDateTime(detail.snapshot.approved_at) }}</div>
                        <div>Disetujui oleh: {{ detail.snapshot.approved_by_user?.name || '-' }}</div>
                    </CardContent>
                </Card>
                <Card class="md:col-span-2">
                    <CardHeader>
                        <CardTitle>Log Periode</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p v-if="detail.audit_logs.length === 0" class="text-muted-foreground">
                            Belum ada audit trail pada periode ini.
                        </p>
                        <div v-for="item in detail.audit_logs" :key="item.id">
                            {{ formatFabaDateTime(item.created_at) }} - {{ item.actor?.name || 'Sistem' }} - {{ item.summary }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
