<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import { formatFabaStatus } from '@/lib/faba';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    year: number;
    stats: {
        total_production: number;
        total_utilization: number;
        current_balance: number;
        negative_periods: number;
    };
    trend: Array<{
        month: number;
        label: string;
        production: number;
        utilization: number;
        closing_balance: number;
    }>;
    pendingApprovals: Array<{
        id: string;
        year: number;
        month: number;
        period_label: string;
        status: string;
    }>;
    warnings: Array<{ month: number; period_label: string; message: string; closing_balance: number }>;
    latestApprovedPeriod?: { id: string; year: number; month: number; status: string; period_label: string } | null;
    topVendors: Array<{ vendor_id: string | null; vendor_name: string; total_quantity: number }>;
}>();

const filterForm = reactive({
    year: props.year,
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Dashboard FABA',
        href: wasteManagementRoutes.faba.dashboard.url(),
    },
];

function applyFilters(): void {
    router.get(wasteManagementRoutes.faba.dashboard(), filterForm);
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Dashboard FABA"
    >
        <Head title="Dashboard FABA" />
        <div class="space-y-6 p-6">
            <Heading
                title="Dashboard FABA"
                description="Ringkasan operasional modul FABA."
            />
            <div class="flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid gap-2">
                    <Label>Tahun</Label>
                    <Input v-model="filterForm.year" type="number" class="w-full md:w-40" />
                </div>
                <Button @click="applyFilters">Terapkan</Button>
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <Card
                    ><CardHeader
                        ><CardTitle>Total Produksi</CardTitle></CardHeader
                    ><CardContent>{{
                        stats.total_production
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Total Pemanfaatan</CardTitle></CardHeader
                    ><CardContent>{{
                        stats.total_utilization
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader><CardTitle>Saldo TPS</CardTitle></CardHeader
                    ><CardContent>{{
                        stats.current_balance
                    }}</CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Periode Negatif</CardTitle></CardHeader
                    ><CardContent>{{
                        stats.negative_periods
                    }}</CardContent></Card
                >
            </div>
            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Periode Approved Terakhir</CardTitle></CardHeader>
                    <CardContent>
                        <p
                            v-if="!latestApprovedPeriod"
                            class="text-sm text-muted-foreground"
                        >
                            Belum ada periode yang disetujui.
                        </p>
                        <div v-else class="space-y-2 text-sm">
                            <p>{{ latestApprovedPeriod.period_label }}</p>
                            <Badge variant="secondary">
                                {{ formatFabaStatus(latestApprovedPeriod.status) }}
                            </Badge>
                            <div>
                                <Link
                                    class="text-primary underline-offset-4 hover:underline"
                                    :href="wasteManagementRoutes.faba.approvals.review([latestApprovedPeriod.year, latestApprovedPeriod.month]).url"
                                >
                                    Lihat review periode
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Tren 12 Bulan</CardTitle></CardHeader>
                    <CardContent>
                        <p
                            v-if="trend.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Belum ada tren produksi dan pemanfaatan pada tahun ini.
                        </p>
                        <div v-else class="space-y-2 text-sm">
                            <div
                                v-for="item in trend"
                                :key="item.month"
                                class="grid grid-cols-[1fr_auto_auto_auto] gap-3 rounded-md border px-3 py-2"
                            >
                                <span>{{ item.label }}</span>
                                <span>Produksi {{ item.production }}</span>
                                <span>Pemanfaatan {{ item.utilization }}</span>
                                <span>Saldo {{ item.closing_balance }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader
                        ><CardTitle>Pending Approval</CardTitle></CardHeader
                    >
                    <CardContent>
                        <p
                            v-if="pendingApprovals.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Tidak ada approval yang menunggu review.
                        </p>
                        <ul v-else class="space-y-2">
                            <li v-for="item in pendingApprovals" :key="item.id">
                                <Link
                                    class="text-primary underline-offset-4 hover:underline"
                                    :href="wasteManagementRoutes.faba.approvals.review([item.year, item.month]).url"
                                >
                                    {{ item.period_label }}
                                </Link>
                                -
                                {{ formatFabaStatus(item.status) }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Peringatan</CardTitle></CardHeader>
                    <CardContent>
                        <p
                            v-if="warnings.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Tidak ada anomali saldo pada periode berjalan.
                        </p>
                        <ul v-else class="space-y-2">
                            <li v-for="item in warnings" :key="`${item.month}-${item.message}`">
                                {{ item.period_label }} - {{ item.message }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Top Vendor</CardTitle></CardHeader>
                    <CardContent>
                        <p
                            v-if="topVendors.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Belum ada data vendor eksternal pada tahun ini.
                        </p>
                        <ul v-else class="space-y-2">
                            <li v-for="item in topVendors" :key="item.vendor_id ?? item.vendor_name">
                                {{ item.vendor_name }} - {{ item.total_quantity }} ton
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </WasteManagementLayout>
</template>
