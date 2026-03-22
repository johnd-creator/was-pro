<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarIcon,
    Building2Icon,
    UserIcon,
    TruckIcon,
    PackageIcon,
    FileTextIcon,
    ArrowLeftIcon,
    SendIcon,
    CheckIcon,
    XIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

function formatDate(dateString: string | null): string {
    if (!dateString) {
        return '-';
    }

    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateOnly(dateString: string | null): string {
    if (!dateString) {
        return '-';
    }

    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatQuantity(value: number | string, unit: string): string {
    return `${parseFloat(String(value)).toFixed(2)} ${unit}`;
}

const quickFacts = computed(() => [
    {
        label: 'Tanggal angkut',
        value: formatDateOnly(props.wasteTransportation.transportation_date),
        icon: CalendarIcon,
        tone: 'text-blue-600 bg-blue-50 border-blue-100',
    },
    {
        label: 'Vendor',
        value: props.wasteTransportation.vendor?.name || 'Belum ditetapkan',
        icon: Building2Icon,
        tone: 'text-emerald-600 bg-emerald-50 border-emerald-100',
    },
    {
        label: 'Kendaraan',
        value: props.wasteTransportation.vehicle_number || 'Belum ditetapkan',
        icon: TruckIcon,
        tone: 'text-orange-600 bg-orange-50 border-orange-100',
    },
    {
        label: 'Kuantitas',
        value: formatQuantity(
            props.wasteTransportation.quantity,
            props.wasteTransportation.unit,
        ),
        icon: PackageIcon,
        tone: 'text-violet-600 bg-violet-50 border-violet-100',
    },
]);

const actionTitle = computed(() => {
    if (props.wasteTransportation.status === 'pending') {
        return 'Transportasi siap diberangkatkan';
    }

    if (props.wasteTransportation.status === 'in_transit') {
        return 'Transportasi sedang berjalan';
    }

    return '';
});

const actionDescription = computed(() => {
    if (props.wasteTransportation.status === 'pending') {
        return 'Periksa detail akhir lalu dispatch saat kendaraan siap berangkat.';
    }

    if (props.wasteTransportation.status === 'in_transit') {
        return 'Tambahkan catatan penerimaan bila pengiriman sudah sampai tujuan.';
    }

    return '';
});

const timelineItems = computed(() => {
    const items = [
        {
            label: 'Dibuat',
            date: props.wasteTransportation.created_at,
            tone: 'bg-slate-900',
            helper: props.wasteTransportation.created_by
                ? `oleh ${props.wasteTransportation.created_by.name}`
                : null,
        },
    ];

    if (props.wasteTransportation.dispatched_at) {
        items.push({
            label: 'Diberangkatkan',
            date: props.wasteTransportation.dispatched_at,
            tone: 'bg-blue-600',
            helper: null,
        });
    }

    if (props.wasteTransportation.delivered_at) {
        items.push({
            label: 'Selesai diantar',
            date: props.wasteTransportation.delivered_at,
            tone: 'bg-emerald-600',
            helper: null,
        });
    }

    if (props.wasteTransportation.status === 'cancelled') {
        items.push({
            label: 'Dibatalkan',
            date: props.wasteTransportation.updated_at,
            tone: 'bg-red-600',
            helper: null,
        });
    }

    return items;
});

function dispatchTransportation(): void {
    if (confirm('Are you sure you want to dispatch this transportation?')) {
        router.post(
            wasteManagementRoutes.transportations.dispatch({
                wasteTransportation: props.wasteTransportation.id,
            }),
        );
    }
}

function markAsDelivered(): void {
    router.post(
        wasteManagementRoutes.transportations.deliver({
            wasteTransportation: props.wasteTransportation.id,
        }),
        {
            delivery_notes: deliveryNotes.value || null,
        },
    );
}

function cancelTransportation(): void {
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

        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8">
            <section class="space-y-4">
                <Button
                    variant="ghost"
                    size="sm"
                    class="-ml-2 w-fit text-muted-foreground"
                    @click="
                        router.get(wasteManagementRoutes.transportations.index())
                    "
                >
                    <ArrowLeftIcon class="mr-2 h-4 w-4" />
                    Kembali ke transportasi
                </Button>

                <div
                    class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="space-y-2">
                        <p class="text-xs font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                            Detail Transportasi
                        </p>
                        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">
                            {{ wasteTransportation.transportation_number }}
                        </h1>
                        <p class="max-w-2xl text-sm text-muted-foreground">
                            Dibuat pada
                            {{ formatDate(wasteTransportation.created_at) }}.
                            Halaman ini merangkum informasi pengiriman, catatan
                            limbah terkait, dan vendor tujuan dalam satu tampilan.
                        </p>
                    </div>

                    <div class="flex items-start">
                        <TransportationStatusBadge
                            :status="wasteTransportation.status"
                            size="lg"
                        />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="fact in quickFacts"
                        :key="fact.label"
                        class="rounded-2xl border p-4 shadow-sm"
                        :class="fact.tone"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold tracking-wide uppercase opacity-80">
                                    {{ fact.label }}
                                </p>
                                <p class="mt-2 text-sm font-semibold leading-5 text-slate-900">
                                    {{ fact.value }}
                                </p>
                            </div>
                            <component
                                :is="fact.icon"
                                class="mt-0.5 h-4 w-4 shrink-0"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Workflow Actions -->
            <Card
                v-if="
                    wasteTransportation.status === 'pending' ||
                    wasteTransportation.status === 'in_transit'
                "
                class="border-slate-200/80 shadow-sm"
            >
                <CardHeader class="pb-4">
                    <CardTitle>{{ actionTitle }}</CardTitle>
                    <CardDescription>
                        {{ actionDescription }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <textarea
                        v-if="wasteTransportation.status === 'in_transit'"
                        v-model="deliveryNotes"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        rows="3"
                        placeholder="Tambahkan catatan penerimaan, kondisi barang, atau konfirmasi dari tujuan."
                    />

                    <div class="flex flex-wrap gap-3">
                        <Button
                            v-if="wasteTransportation.status === 'pending'"
                            class="min-w-44"
                            @click="dispatchTransportation"
                        >
                            <SendIcon class="mr-2 h-4 w-4" />
                            Dispatch transportasi
                        </Button>

                        <Button
                            v-if="wasteTransportation.status === 'in_transit'"
                            class="min-w-44"
                            @click="markAsDelivered"
                        >
                            <CheckIcon class="mr-2 h-4 w-4" />
                            Tandai selesai
                        </Button>

                        <Button
                            variant="destructive"
                            class="min-w-32"
                            @click="cancelTransportation"
                        >
                            <XIcon class="mr-2 h-4 w-4" />
                            Batal
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <!-- Transportation Details -->
                <Card class="border-slate-200/80 shadow-sm">
                    <CardHeader class="pb-4">
                        <CardTitle>Ringkasan Transportasi</CardTitle>
                        <CardDescription>
                            Informasi inti pengiriman yang paling sering dicek
                            saat operasional.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <div class="mb-3 flex items-center gap-2 text-slate-700">
                                    <CalendarIcon class="h-4 w-4" />
                                    <p class="text-sm font-semibold">
                                        Tanggal transportasi
                                    </p>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        formatDateOnly(
                                            wasteTransportation.transportation_date,
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <div class="mb-3 flex items-center gap-2 text-slate-700">
                                    <TruckIcon class="h-4 w-4" />
                                    <p class="text-sm font-semibold">
                                        Kendaraan
                                    </p>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.vehicle_number ||
                                        'Belum ditetapkan'
                                    }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <div class="mb-3 flex items-center gap-2 text-slate-700">
                                    <UserIcon class="h-4 w-4" />
                                    <p class="text-sm font-semibold">
                                        Pengemudi
                                    </p>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.driver_name ||
                                        'Belum ditetapkan'
                                    }}
                                </p>
                                <p
                                    v-if="wasteTransportation.driver_phone"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ wasteTransportation.driver_phone }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <div class="mb-3 flex items-center gap-2 text-slate-700">
                                    <PackageIcon class="h-4 w-4" />
                                    <p class="text-sm font-semibold">
                                        Kuantitas
                                    </p>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        formatQuantity(
                                            wasteTransportation.quantity,
                                            wasteTransportation.unit,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                wasteTransportation.notes ||
                                wasteTransportation.delivery_notes
                            "
                            class="grid gap-4 md:grid-cols-2"
                        >
                            <div
                                v-if="wasteTransportation.notes"
                                class="rounded-2xl border border-slate-100 bg-white p-4"
                            >
                                <div class="mb-3 flex items-center gap-2 text-slate-700">
                                    <FileTextIcon class="h-4 w-4" />
                                    <p class="text-sm font-semibold">
                                        Catatan transportasi
                                    </p>
                                </div>
                                <p class="text-sm leading-6 text-muted-foreground">
                                    {{ wasteTransportation.notes }}
                                </p>
                            </div>

                            <div
                                v-if="wasteTransportation.delivery_notes"
                                class="rounded-2xl border border-slate-100 bg-white p-4"
                            >
                                <div class="mb-3 flex items-center gap-2 text-slate-700">
                                    <FileTextIcon class="h-4 w-4" />
                                    <p class="text-sm font-semibold">
                                        Catatan penerimaan
                                    </p>
                                </div>
                                <p class="text-sm leading-6 text-muted-foreground">
                                    {{ wasteTransportation.delivery_notes }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Waste Record & Vendor -->
                <div class="space-y-6">
                    <!-- Waste Record -->
                    <Card class="border-slate-200/80 shadow-sm">
                        <CardHeader class="pb-4">
                            <CardTitle>Catatan Limbah Terkait</CardTitle>
                            <CardDescription>
                                Sumber limbah yang diangkut pada transportasi ini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <p class="text-sm font-medium">Nomor catatan</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.waste_record
                                            ?.record_number || '-'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-sm font-medium">Jenis limbah</p>
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
                                <p class="text-sm font-medium">Tanggal catatan</p>
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
                                <p class="text-sm font-medium">Status kedaluwarsa</p>
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
                    <Card class="border-slate-200/80 shadow-sm">
                        <CardHeader class="pb-4">
                            <CardTitle>Vendor Tujuan</CardTitle>
                            <CardDescription>
                                Informasi mitra pengangkutan atau penerima limbah.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <p class="text-sm font-medium">Nama vendor</p>
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
                                    Kontak PIC
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        wasteTransportation.vendor
                                            .contact_person
                                    }}
                                </p>
                            </div>

                            <div v-if="wasteTransportation.vendor?.phone">
                                <p class="text-sm font-medium">Telepon</p>
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
            <Card class="border-slate-200/80 shadow-sm">
                <CardHeader class="pb-4">
                    <CardTitle>Timeline Aktivitas</CardTitle>
                    <CardDescription>
                        Jejak perubahan status transportasi dari awal hingga kondisi saat ini.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="(item, index) in timelineItems"
                            :key="`${item.label}-${index}`"
                            class="flex gap-4"
                        >
                            <div class="flex flex-col items-center">
                                <div class="h-3 w-3 rounded-full" :class="item.tone"></div>
                                <div
                                    v-if="index !== timelineItems.length - 1"
                                    class="w-0.5 flex-1 bg-muted"
                                ></div>
                            </div>
                            <div :class="index !== timelineItems.length - 1 ? 'pb-4' : ''">
                                <p class="text-sm font-medium">{{ item.label }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatDate(item.date) }}
                                </p>
                                <p v-if="item.helper" class="text-xs text-muted-foreground">
                                    {{ item.helper }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </WasteManagementLayout>
</template>
