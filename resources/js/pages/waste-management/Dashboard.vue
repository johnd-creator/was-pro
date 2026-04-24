<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FileTextIcon,
    TruckIcon,
    AlertTriangleIcon,
    ClockIcon,
    CheckCircleIcon,
    PackageIcon,
    AlertCircleIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface Stats {
    total_waste_records: number;
    draft_records: number;
    pending_records: number;
    approved_records: number;
    rejected_records: number;
    total_transportations: number;
    pending_transportations: number;
    in_transit_transportations: number;
    delivered_transportations: number;
    expired_waste: number;
    expiring_soon_waste: number;
    total_waste_types: number;
    total_categories: number;
    total_vendors: number;
}

interface WasteByCategory {
    category: string;
    quantity: number;
    unit: string;
}

interface WasteTrend {
    date: string;
    quantity: number;
    unit: string;
}

interface TransportationByStatus {
    pending: number;
    in_transit: number;
    delivered: number;
    cancelled: number;
}

interface Activity {
    type: string;
    icon: string;
    title: string;
    description: string;
    user: string;
    created_at: string;
}

interface WasteRecord {
    id: string;
    record_number: string;
    waste_type?: {
        name: string;
        category?: {
            name: string;
        };
    };
    quantity: number;
    unit: string;
    expiry_date: string | null;
    submitted_by_user?: {
        name: string;
    };
    submitted_at?: string;
    created_at?: string;
}

type Props = {
    stats: Stats;
    wasteByCategory: WasteByCategory[];
    wasteTrends: WasteTrend[];
    transportationByStatus: TransportationByStatus;
    recentActivities: Activity[];
    expiringSoon: WasteRecord[];
    expiredWaste: WasteRecord[];
    pendingApprovals: WasteRecord[];
    dateRange: {
        start: string;
        end: string;
    };
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/waste-management/dashboard',
    },
];

// Computed properties for quick access
const hasAlerts = computed(() => {
    return props.stats.expired_waste > 0 || props.stats.expiring_soon_waste > 0;
});

const hasPendingApprovals = computed(() => {
    return props.pendingApprovals.length > 0;
});
</script>

<template>
    <WasteManagementLayout :breadcrumbs="breadcrumbItems" title="Dashboard">
        <Head title="Dashboard - Waste Management" />

        <div class="space-y-6">
            <Heading
                title="Waste Management Dashboard"
                description="Overview of your waste management system"
            />

            <!-- Alert Banners -->
            <Card
                v-if="hasAlerts"
                class="border-orange-200 bg-orange-50 focus-within:ring-2 focus-within:ring-orange-500 focus-within:ring-offset-2 dark:border-orange-800 dark:bg-orange-900/20"
            >
                <CardContent class="pt-6">
                    <div class="flex items-center gap-3">
                        <AlertTriangleIcon
                            class="h-5 w-5 text-orange-800 dark:text-orange-300"
                            aria-label="Alert: Attention required"
                        />
                        <div class="flex-1">
                            <p
                                class="text-sm font-medium text-orange-900 dark:text-orange-100"
                            >
                                Attention Required:
                                {{ stats.expired_waste }} expired waste records,
                                {{ stats.expiring_soon_waste }} expiring soon
                            </p>
                            <p
                                class="mt-1 text-sm text-orange-800 dark:text-orange-200"
                            >
                                Please prioritize transportation for expired and
                                expiring waste.
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="default"
                            class="focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
                            @click="
                                router.get(
                                    wasteManagementRoutes.haulings.create(),
                                )
                            "
                        >
                            Schedule Transportation
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Waste Records -->
                <Card
                    class="transition-shadow transition-transform duration-200 hover:shadow-md active:scale-[0.98]"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Records</CardTitle
                        >
                        <FileTextIcon
                            class="h-4 w-4 text-muted-foreground"
                            aria-label="Total records icon"
                        />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold tabular-nums">
                            {{ stats.total_waste_records }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.approved_records }} approved,
                            {{ stats.pending_records }} pending
                        </p>
                    </CardContent>
                </Card>

                <!-- Transportations -->
                <Card
                    class="transition-shadow transition-transform duration-200 hover:shadow-md active:scale-[0.98]"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Transportations</CardTitle
                        >
                        <TruckIcon
                            class="h-4 w-4 text-muted-foreground"
                            aria-label="Transportations icon"
                        />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold tabular-nums">
                            {{ stats.total_transportations }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.in_transit_transportations }} in transit,
                            {{ stats.delivered_transportations }} delivered
                        </p>
                    </CardContent>
                </Card>

                <!-- Expired Waste -->
                <Card
                    class="transition-shadow transition-transform duration-200 hover:shadow-md active:scale-[0.98]"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Expired Waste</CardTitle
                        >
                        <AlertCircleIcon
                            class="h-4 w-4 text-red-500"
                            aria-label="Expired waste alert icon"
                        />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold text-red-600 tabular-nums dark:text-red-400"
                        >
                            {{ stats.expired_waste }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Requires immediate attention
                        </p>
                    </CardContent>
                </Card>

                <!-- Expiring Soon -->
                <Card
                    class="transition-shadow transition-transform duration-200 hover:shadow-md active:scale-[0.98]"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Expiring Soon</CardTitle
                        >
                        <ClockIcon
                            class="h-4 w-4 text-orange-500"
                            aria-label="Expiring soon clock icon"
                        />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold text-orange-600 tabular-nums dark:text-orange-400"
                        >
                            {{ stats.expiring_soon_waste }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Within next 7 days
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column (2/3 width) -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Tabs for different views -->
                    <Tabs default-value="activities">
                        <TabsList>
                            <TabsTrigger value="activities">
                                Recent Activities
                            </TabsTrigger>
                            <TabsTrigger
                                value="pending"
                                v-if="hasPendingApprovals"
                            >
                                Pending Approvals ({{
                                    pendingApprovals.length
                                }})
                            </TabsTrigger>
                        </TabsList>

                        <!-- Recent Activities Tab -->
                        <TabsContent value="activities">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent Activities</CardTitle>
                                    <CardDescription>
                                        Latest waste records and transportations
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-4">
                                        <div
                                            v-for="activity in recentActivities"
                                            :key="activity.description"
                                            class="flex items-start gap-4 border-b pb-4 last:border-0 last:pb-0"
                                        >
                                            <div class="text-2xl">
                                                {{ activity.icon }}
                                            </div>
                                            <div class="flex-1 space-y-1">
                                                <p class="text-sm font-medium">
                                                    {{ activity.title }}
                                                </p>
                                                <p
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    {{ activity.description }}
                                                </p>
                                                <div
                                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                                >
                                                    <span>{{
                                                        activity.user
                                                    }}</span>
                                                    <span>•</span>
                                                    <span>{{
                                                        activity.created_at
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <!-- Pending Approvals Tab -->
                        <TabsContent value="pending">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Pending Approvals</CardTitle>
                                    <CardDescription>
                                        Waste records awaiting your approval
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div
                                        v-if="pendingApprovals.length === 0"
                                        class="py-8 text-center text-muted-foreground"
                                    >
                                        No pending approvals
                                    </div>
                                    <div v-else class="space-y-4">
                                        <div
                                            v-for="record in pendingApprovals"
                                            :key="record.id"
                                            class="flex items-center justify-between border-b pb-4 last:border-0 last:pb-0"
                                        >
                                            <div class="flex-1">
                                                <p class="text-sm font-medium">
                                                    {{ record.record_number }}
                                                </p>
                                                <p
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    {{
                                                        record.waste_type?.name
                                                    }}
                                                    ({{ record.quantity }}
                                                    {{ record.unit }})
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Submitted by
                                                    {{
                                                        record.submitted_by_user
                                                            ?.name
                                                    }}
                                                    •
                                                    {{
                                                        new Date(
                                                            record.submitted_at ||
                                                                record.created_at ||
                                                                new Date().toISOString(),
                                                        ).toLocaleDateString()
                                                    }}
                                                </p>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    router.get(
                                                        wasteManagementRoutes.records.pendingApproval(),
                                                    )
                                                "
                                            >
                                                Review
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>

                <!-- Right Column (1/3 width) -->
                <div class="space-y-6">
                    <!-- Expiring Soon Alert -->
                    <Card
                        v-if="expiringSoon.length > 0"
                        class="transition-shadow duration-200 hover:shadow-md"
                    >
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-sm">
                                <ClockIcon
                                    class="h-4 w-4 text-orange-500"
                                    aria-label="Expiring soon icon"
                                />
                                Expiring Soon
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="record in expiringSoon"
                                :key="record.id"
                                class="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ record.record_number }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ record.waste_type?.name }}
                                    </p>
                                    <ExpiryBadge
                                        :expiry-date="record.expiry_date"
                                        size="sm"
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="default"
                                    class="min-h-[44px] focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
                                    @click="
                                        router.get(
                                            wasteManagementRoutes.records.show(
                                                record.id,
                                            ),
                                        )
                                    "
                                    :aria-label="`View waste record details for ${record.record_number}`"
                                >
                                    View
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Expired Waste Alert -->
                    <Card
                        v-if="expiredWaste.length > 0"
                        class="transition-shadow duration-200 hover:shadow-md"
                    >
                        <CardHeader>
                            <CardTitle
                                class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400"
                            >
                                <AlertTriangleIcon
                                    class="h-4 w-4"
                                    aria-label="Expired waste alert icon"
                                />
                                Expired
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="record in expiredWaste"
                                :key="record.id"
                                class="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ record.record_number }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ record.waste_type?.name }}
                                    </p>
                                    <ExpiryBadge
                                        :expiry-date="record.expiry_date"
                                        size="sm"
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="default"
                                    class="min-h-[44px] focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
                                    @click="
                                        router.get(
                                            wasteManagementRoutes.records.show(
                                                record.id,
                                            ),
                                        )
                                    "
                                    :aria-label="`View waste record details for ${record.record_number}`"
                                >
                                    View
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Quick Actions -->
                    <Card
                        class="transition-shadow duration-200 hover:shadow-md"
                    >
                        <CardHeader>
                            <CardTitle class="text-sm">Quick Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <Button
                                variant="outline"
                                class="min-h-[44px] w-full justify-start focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.records.create(),
                                    )
                                "
                            >
                                <FileTextIcon
                                    class="mr-2 h-4 w-4"
                                    aria-hidden="true"
                                />
                                Create Waste Record
                            </Button>
                            <Button
                                variant="outline"
                                class="min-h-[44px] w-full justify-start focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.haulings.create(),
                                    )
                                "
                            >
                                <TruckIcon
                                    class="mr-2 h-4 w-4"
                                    aria-hidden="true"
                                />
                                Schedule Transportation
                            </Button>
                            <Button
                                variant="outline"
                                class="min-h-[44px] w-full justify-start focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                                @click="
                                    router.get(
                                        wasteManagementRoutes.records.pendingApproval(),
                                    )
                                "
                                v-if="
                                    $page.props.auth?.user?.permissions?.includes(
                                        'waste_records.approve',
                                    )
                                "
                            >
                                <CheckCircleIcon
                                    class="mr-2 h-4 w-4"
                                    aria-hidden="true"
                                />
                                Review Pending Records
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Waste by Category -->
                <Card class="transition-shadow duration-200 hover:shadow-md">
                    <CardHeader>
                        <CardTitle>Waste by Category</CardTitle>
                        <CardDescription>
                            Total waste recorded by category
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="wasteByCategory.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            No data available
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="item in wasteByCategory"
                                :key="item.category"
                                class="flex items-center justify-between"
                            >
                                <div class="flex-1">
                                    <p class="text-sm font-medium">
                                        {{ item.category }}
                                    </p>
                                    <div
                                        class="mt-1 h-2 overflow-hidden rounded-full bg-secondary"
                                    >
                                        <div
                                            class="h-full bg-primary"
                                            :style="{
                                                width: `${(item.quantity / Math.max(...wasteByCategory.map((w) => w.quantity))) * 100}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>
                                <p class="ml-4 text-sm font-medium">
                                    {{ item.quantity.toFixed(1) }}
                                    {{ item.unit }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Transportation Status -->
                <Card class="transition-shadow duration-200 hover:shadow-md">
                    <CardHeader>
                        <CardTitle>Transportation Status</CardTitle>
                        <CardDescription>
                            Current status of all transportations
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="stats.total_transportations === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            No transportations yet
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(
                                    count, status
                                ) in transportationByStatus"
                                :key="status"
                                class="flex items-center justify-between"
                            >
                                <div class="flex-1">
                                    <p class="text-sm font-medium capitalize">
                                        {{ status.replace('_', ' ') }}
                                    </p>
                                    <div
                                        class="mt-1 h-2 overflow-hidden rounded-full bg-secondary"
                                    >
                                        <div
                                            class="h-full"
                                            :class="{
                                                'bg-blue-500':
                                                    status === 'pending',
                                                'bg-yellow-500':
                                                    status === 'in_transit',
                                                'bg-green-500':
                                                    status === 'delivered',
                                                'bg-red-500':
                                                    status === 'cancelled',
                                            }"
                                            :style="{
                                                width: `${(count / stats.total_transportations) * 100}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>
                                <p class="ml-4 text-sm font-medium">
                                    {{ count }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Master Data Summary -->
            <Card class="transition-shadow duration-200 hover:shadow-md">
                <CardHeader>
                    <CardTitle>Master Data Overview</CardTitle>
                    <CardDescription>
                        Summary of master data entities
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div
                            class="flex items-center gap-4 rounded-lg border p-4"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900"
                            >
                                <PackageIcon
                                    class="h-5 w-5 text-purple-600 dark:text-purple-400"
                                    aria-label="Waste types icon"
                                />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Waste Types
                                </p>
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ stats.total_waste_types }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-4 rounded-lg border p-4"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900"
                            >
                                <FileTextIcon
                                    class="h-5 w-5 text-blue-600 dark:text-blue-400"
                                    aria-label="Categories icon"
                                />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Categories
                                </p>
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ stats.total_categories }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-4 rounded-lg border p-4"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900"
                            >
                                <TruckIcon
                                    class="h-5 w-5 text-green-600 dark:text-green-400"
                                    aria-label="Vendors icon"
                                />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Vendors
                                </p>
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ stats.total_vendors }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
