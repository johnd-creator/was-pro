<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarIcon,
    UserIcon,
    TruckIcon,
    PackageIcon,
    FileTextIcon,
    ArrowLeftIcon,
    SendIcon,
    CheckIcon,
    XIcon,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import ExpiryBadge from '@/components/waste-management/ExpiryBadge.vue';
import TransportationStatusBadge from '@/components/waste-management/TransportationStatusBadge.vue';
import WasteManagementLayout from '@/layouts/waste-management/Layout.vue';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

interface Vendor {
    id: string;
    name: string;
    contact_person?: string;
    phone?: string;
    email?: string;
}

interface WasteRecord {
    id: string;
    record_number: string;
    date: string;
    expiry_date: string | null;
    waste_type?: {
        name: string;
        category?: {
            name: string;
        };
    };
    quantity: number;
    unit: string;
}

interface User {
    name: string;
}

interface WasteTransportation {
    id: string;
    transportation_number: string;
    transportation_date: string;
    quantity: number;
    unit: string;
    vehicle_number?: string;
    driver_name?: string;
    driver_phone?: string;
    status: 'pending' | 'in_transit' | 'delivered' | 'cancelled';
    notes?: string;
    delivery_notes?: string;
    dispatched_at?: string;
    delivered_at?: string;
    created_at: string;
    updated_at: string;
    waste_record?: WasteRecord;
    vendor?: Vendor;
    created_by?: User;
}

type Props = {
    wasteTransportation: WasteTransportation;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Waste Transportations',
        href: '/waste-management/transportations',
    },
    {
        title: `Transportation #${props.wasteTransportation.transportation_number}`,
        href: `/waste-management/transportations/${props.wasteTransportation.id}`,
    },
];

const deliveryNotes = ref('');

function formatDate(dateString: string | null) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateOnly(dateString: string) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function dispatchTransportation() {
    if (confirm('Are you sure you want to dispatch this transportation?')) {
        router.post(
            wasteManagementRoutes.transportations.dispatch({
                wasteTransportation: props.wasteTransportation.id,
            }),
        );
    }
}

function markAsDelivered() {
    router.post(
        wasteManagementRoutes.transportations.deliver({
            wasteTransportation: props.wasteTransportation.id,
        }),
        {
            delivery_notes: deliveryNotes.value || null,
        },
    );
}

function cancelTransportation() {
    if (confirm('Are you sure you want to cancel this transportation?')) {
        router.post(
            wasteManagementRoutes.transportations.cancel({
                wasteTransportation: props.wasteTransportation.id,
            }),
        );
    }
}
</script>

<template>
    <WasteManagementLayout
        :breadcrumbs="breadcrumbItems"
        title="Transportation Details"
    >
        <Head
            :title="`Transportation #${wasteTransportation.transportation_number} - Waste Management`"
        />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="
                                router.get(
                                    wasteManagementRoutes.transportations.index(),
                                )
                            "
                        >
                            <ArrowLeftIcon class="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <div>
                            <h1 class="text-2xl font-bold">
                                {{ wasteTransportation.transportation_number }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                Created on
                                {{ formatDate(wasteTransportation.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
                <TransportationStatusBadge
                    :status="wasteTransportation.status"
                    size="lg"
                />
            </div>

            <!-- Workflow Actions -->
            <Card
                v-if="
                    wasteTransportation.status === 'pending' ||
                    wasteTransportation.status === 'in_transit'
                "
            >
                <CardHeader>
                    <CardTitle>Actions</CardTitle>
                    <CardDescription>
                        Manage this transportation workflow
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex gap-3">
                    <Button
                        v-if="wasteTransportation.status === 'pending'"
                        @click="dispatchTransportation"
                    >
                        <SendIcon class="mr-2 h-4 w-4" />
                        Dispatch Transportation
                    </Button>

                    <Button
                        v-if="wasteTransportation.status === 'in_transit'"
                        @click="markAsDelivered"
                    >
                        <CheckIcon class="mr-2 h-4 w-4" />
                        Mark as Delivered
                    </Button>

                    <Button variant="destructive" @click="cancelTransportation">
                        <XIcon class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                </CardContent>
            </Card>

            <!-- Delivery Notes (for in_transit status) -->
            <Card v-if="wasteTransportation.status === 'in_transit'">
                <CardHeader>
                    <CardTitle>Delivery Notes</CardTitle>
                    <CardDescription>
                        Add notes when marking as delivered (optional)
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <textarea
                        v-model="deliveryNotes"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        rows="3"
                        placeholder="Enter delivery notes, confirmation details, etc..."
                    />
                </CardContent>
            </Card>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Transportation Details -->
                <Card>
                    <CardHeader>
                        <CardTitle>Transportation Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-3">
                            <CalendarIcon
                                class="h-5 w-5 text-muted-foreground"
                            />
                            <div>
                                <p class="text-sm font-medium">
                                    Transportation Date
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        formatDateOnly(
                                            wasteTransportation.transportation_date,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <Separator />

                        <div class="flex items-center gap-3">
                            <TruckIcon class="h-5 w-5 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">
                                    Vehicle Information
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.vehicle_number ||
                                        'Not assigned'
                                    }}
                                </p>
                            </div>
                        </div>

                        <Separator />

                        <div class="flex items-center gap-3">
                            <UserIcon class="h-5 w-5 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">
                                    Driver Information
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.driver_name ||
                                        'Not assigned'
                                    }}
                                </p>
                                <p
                                    v-if="wasteTransportation.driver_phone"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ wasteTransportation.driver_phone }}
                                </p>
                            </div>
                        </div>

                        <Separator v-if="wasteTransportation.notes" />

                        <div
                            v-if="wasteTransportation.notes"
                            class="flex items-center gap-3"
                        >
                            <FileTextIcon
                                class="h-5 w-5 text-muted-foreground"
                            />
                            <div>
                                <p class="text-sm font-medium">Notes</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ wasteTransportation.notes }}
                                </p>
                            </div>
                        </div>

                        <Separator v-if="wasteTransportation.delivery_notes" />

                        <div
                            v-if="wasteTransportation.delivery_notes"
                            class="flex items-center gap-3"
                        >
                            <FileTextIcon
                                class="h-5 w-5 text-muted-foreground"
                            />
                            <div>
                                <p class="text-sm font-medium">
                                    Delivery Notes
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ wasteTransportation.delivery_notes }}
                                </p>
                            </div>
                        </div>

                        <Separator />

                        <div class="flex items-center gap-3">
                            <PackageIcon
                                class="h-5 w-5 text-muted-foreground"
                            />
                            <div>
                                <p class="text-sm font-medium">Quantity</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        parseFloat(
                                            String(
                                                wasteTransportation.quantity,
                                            ),
                                        ).toFixed(2)
                                    }}
                                    {{ wasteTransportation.unit }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Waste Record & Vendor -->
                <div class="space-y-6">
                    <!-- Waste Record -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Waste Record</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <p class="text-sm font-medium">Record Number</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.waste_record
                                            ?.record_number || '-'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-sm font-medium">Waste Type</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.waste_record
                                            ?.waste_type?.name || '-'
                                    }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        wasteTransportation.waste_record
                                            ?.waste_type?.category?.name || '-'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-sm font-medium">Record Date</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.waste_record
                                            ? formatDateOnly(
                                                  wasteTransportation
                                                      .waste_record.date,
                                              )
                                            : '-'
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="
                                    wasteTransportation.waste_record
                                        ?.expiry_date
                                "
                            >
                                <p class="text-sm font-medium">Expiry Status</p>
                                <ExpiryBadge
                                    :expiry-date="
                                        wasteTransportation.waste_record
                                            .expiry_date
                                    "
                                    size="sm"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Vendor -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Vendor</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <p class="text-sm font-medium">Vendor Name</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.vendor?.name || '-'
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="
                                    wasteTransportation.vendor?.contact_person
                                "
                            >
                                <p class="text-sm font-medium">
                                    Contact Person
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.vendor
                                            .contact_person
                                    }}
                                </p>
                            </div>

                            <div v-if="wasteTransportation.vendor?.phone">
                                <p class="text-sm font-medium">Phone</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ wasteTransportation.vendor.phone }}
                                </p>
                            </div>

                            <div v-if="wasteTransportation.vendor?.email">
                                <p class="text-sm font-medium">Email</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ wasteTransportation.vendor.email }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Timeline -->
            <Card>
                <CardHeader>
                    <CardTitle>Activity Timeline</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div
                                    class="h-3 w-3 rounded-full bg-primary"
                                ></div>
                                <div class="w-0.5 flex-1 bg-muted"></div>
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium">Created</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        formatDate(
                                            wasteTransportation.created_at,
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="wasteTransportation.created_by"
                                    class="text-xs text-muted-foreground"
                                >
                                    by {{ wasteTransportation.created_by.name }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="wasteTransportation.dispatched_at"
                            class="flex gap-4"
                        >
                            <div class="flex flex-col items-center">
                                <div
                                    class="h-3 w-3 rounded-full bg-primary"
                                ></div>
                                <div class="w-0.5 flex-1 bg-muted"></div>
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium">Dispatched</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        formatDate(
                                            wasteTransportation.dispatched_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="wasteTransportation.delivered_at"
                            class="flex gap-4"
                        >
                            <div class="flex flex-col items-center">
                                <div
                                    class="h-3 w-3 rounded-full bg-green-600"
                                ></div>
                            </div>
                            <div>
                                <p class="text-sm font-medium">Delivered</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        formatDate(
                                            wasteTransportation.delivered_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="wasteTransportation.status === 'cancelled'"
                            class="flex gap-4"
                        >
                            <div class="flex flex-col items-center">
                                <div
                                    class="h-3 w-3 rounded-full bg-red-600"
                                ></div>
                            </div>
                            <div>
                                <p class="text-sm font-medium">Cancelled</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        formatDate(
                                            wasteTransportation.updated_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
