<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

defineProps<{
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
    latestApprovedPeriod?: { id: string; year: number; month: number; status: string } | null;
    topVendors: Array<{ vendor_id: string | null; vendor_name: string; total_quantity: number }>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Dashboard FABA',
        href: wasteManagementRoutes.faba.dashboard.url(),
    },
];
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
                                {{ item.period_label }} -
                                {{ item.status }}
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
