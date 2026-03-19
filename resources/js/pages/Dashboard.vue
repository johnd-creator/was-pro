<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Clock3,
    FileText,
    ShieldAlert,
    Truck,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, onMounted, ref, toRefs } from 'vue';
import type { Component } from 'vue';
import SkeletonCard from '@/components/dashboard/SkeletonCard.vue';
import Heading from '@/components/Heading.vue';

// Lazy load components for better performance
const ActivityFeed = defineAsyncComponent(
    () => import('@/components/dashboard/ActivityFeed.vue'),
);
const ApprovalList = defineAsyncComponent(
    () => import('@/components/dashboard/ApprovalList.vue'),
);
const CategoryChart = defineAsyncComponent(
    () => import('@/components/dashboard/CategoryChart.vue'),
);
const StatusDistribution = defineAsyncComponent(
    () => import('@/components/dashboard/StatusDistribution.vue'),
);

// Animation setup
const { prefersReducedMotion } = useReducedMotion();
const isMounted = ref(false);

onMounted(() => {
    // Staggered entrance animation
    const delay = prefersReducedMotion.value ? 0 : 100;
    setTimeout(() => {
        isMounted.value = true;
    }, delay);
});
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useReducedMotion } from '@/composables/useReducedMotion';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard as dashboardRoute } from '@/routes';
import wasteManagementRoutes from '@/routes/waste-management';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboardRoute.url(),
    },
];

interface Stats {
    total_waste_records: number;
    approved_records: number;
    pending_records: number;
    total_transportations: number;
    in_transit_transportations: number;
    expired_waste: number;
    expiring_soon_waste: number;
}

interface Activity {
    id: string;
    type: 'waste_record' | 'transportation';
    action:
        | 'created'
        | 'submitted'
        | 'approved'
        | 'rejected'
        | 'dispatched'
        | 'delivered';
    description: string;
    user_name: string;
    created_at: string;
    link?: string;
}

interface PendingApproval {
    id: string;
    record_number: string;
    waste_type: string;
    category: string;
    quantity: number;
    unit: string;
    submitted_by: string;
    submitted_at: string;
}

interface CategoryData {
    category: string;
    count: number;
    percentage: number;
}

interface StatusData {
    status: string;
    count: number;
    color: string;
    percentage?: number;
}

interface Props {
    stats: Stats;
    recentActivities: Activity[];
    pendingApprovals: PendingApproval[];
    wasteByCategory: CategoryData[];
    transportationByStatus: StatusData[];
}

interface PriorityCard {
    title: string;
    value: number;
    description: string;
    actionLabel: string;
    href: string;
    icon: Component;
    tone: 'critical' | 'warning' | 'info' | 'success';
}

interface QuickAction {
    title: string;
    description: string;
    href: string;
    icon: Component;
}

const props = defineProps<Props>();
const {
    stats,
    recentActivities,
    pendingApprovals,
    wasteByCategory,
    transportationByStatus,
} = toRefs(props);

const hasAlerts = computed(
    () => props.stats.expired_waste > 0 || props.stats.expiring_soon_waste > 0,
);

const hasPendingApprovals = computed(() => props.pendingApprovals.length > 0);

const todayLabel = computed(() =>
    new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date()),
);

const dashboardStatus = computed(() => {
    if (props.stats.expired_waste > 0 || hasPendingApprovals.value) {
        return {
            label: 'Perlu perhatian',
            description:
                'Ada risiko kepatuhan atau keputusan yang perlu ditindaklanjuti hari ini.',
        };
    }

    if (props.stats.expiring_soon_waste > 0) {
        return {
            label: 'Perlu pemantauan',
            description:
                'Ada catatan yang mendekati batas simpan dan perlu dipersiapkan tindak lanjutnya.',
        };
    }

    return {
        label: 'Terkendali',
        description:
            'Operasional limbah berjalan stabil. Gunakan dashboard ini untuk memantau ritme kerja harian.',
    };
});

const priorityCards = computed<PriorityCard[]>(() => [
    {
        title: 'Melewati batas simpan',
        value: props.stats.expired_waste,
        description:
            props.stats.expired_waste > 0
                ? 'Catatan ini membutuhkan tindak lanjut segera agar tidak menjadi risiko kepatuhan.'
                : 'Belum ada catatan yang melewati batas simpan saat ini.',
        actionLabel:
            props.stats.expired_waste > 0
                ? 'Tinjau catatan berisiko'
                : 'Pantau semua catatan',
        href: wasteManagementRoutes.records.index().url,
        icon: ShieldAlert,
        tone: props.stats.expired_waste > 0 ? 'critical' : 'success',
    },
    {
        title: 'Mendekati batas simpan',
        value: props.stats.expiring_soon_waste,
        description:
            props.stats.expiring_soon_waste > 0
                ? 'Siapkan jadwal transportasi untuk catatan yang akan mendekati batas simpan dalam 7 hari.'
                : 'Tidak ada catatan yang mendekati batas simpan dalam 7 hari ke depan.',
        actionLabel:
            props.stats.expiring_soon_waste > 0
                ? 'Jadwalkan transportasi'
                : 'Lihat transportasi',
        href:
            props.stats.expiring_soon_waste > 0
                ? wasteManagementRoutes.transportations.create().url
                : wasteManagementRoutes.transportations.index().url,
        icon: Clock3,
        tone: props.stats.expiring_soon_waste > 0 ? 'warning' : 'info',
    },
    {
        title: 'Menunggu keputusan',
        value: props.pendingApprovals.length,
        description:
            props.pendingApprovals.length > 0
                ? 'Catatan ini sedang menunggu persetujuan atau penolakan dari approver.'
                : 'Tidak ada antrian persetujuan yang menunggu keputusan saat ini.',
        actionLabel:
            props.pendingApprovals.length > 0
                ? 'Buka antrian persetujuan'
                : 'Pantau catatan limbah',
        href:
            props.pendingApprovals.length > 0
                ? wasteManagementRoutes.records.pendingApproval().url
                : wasteManagementRoutes.records.index().url,
        icon: CheckCircle2,
        tone: props.pendingApprovals.length > 0 ? 'warning' : 'success',
    },
    {
        title: 'Transportasi aktif',
        value: props.stats.in_transit_transportations,
        description:
            props.stats.in_transit_transportations > 0
                ? 'Pengiriman limbah sedang berjalan dan perlu dipantau sampai selesai.'
                : 'Belum ada pengiriman aktif saat ini.',
        actionLabel:
            props.stats.in_transit_transportations > 0
                ? 'Pantau transportasi'
                : 'Buat transportasi baru',
        href:
            props.stats.in_transit_transportations > 0
                ? wasteManagementRoutes.transportations.index().url
                : wasteManagementRoutes.transportations.create().url,
        icon: Truck,
        tone: 'info',
    },
]);

const quickActions = computed<QuickAction[]>(() => {
    const actions: QuickAction[] = [
        {
            title: 'Buat catatan limbah',
            description:
                'Mulai pencatatan limbah baru untuk operasional hari ini.',
            href: wasteManagementRoutes.records.create().url,
            icon: FileText,
        },
        {
            title: 'Jadwalkan transportasi',
            description:
                'Rencanakan pengangkutan untuk catatan yang siap diproses.',
            href: wasteManagementRoutes.transportations.create().url,
            icon: Truck,
        },
    ];

    actions.push({
        title: hasPendingApprovals.value
            ? 'Tinjau persetujuan'
            : 'Lihat catatan limbah',
        description: hasPendingApprovals.value
            ? 'Buka antrian keputusan yang membutuhkan tindak lanjut approver.'
            : 'Pantau seluruh catatan limbah dan status prosesnya.',
        href: hasPendingApprovals.value
            ? wasteManagementRoutes.records.pendingApproval().url
            : wasteManagementRoutes.records.index().url,
        icon: ArrowRight,
    });

    return actions;
});

const topCategory = computed(() => {
    const [category] = props.wasteByCategory;

    return category ?? null;
});

function priorityCardClasses(tone: PriorityCard['tone']): string {
    const classes = {
        critical:
            'border-0 bg-gradient-to-br from-red-500 via-red-600 to-red-700 text-white shadow-xl shadow-red-500/30 hover:shadow-2xl hover:shadow-red-500/40 hover:scale-[1.02]',
        warning:
            'border-0 bg-gradient-to-br from-orange-400 via-orange-500 to-orange-600 text-white shadow-xl shadow-orange-500/30 hover:shadow-2xl hover:shadow-orange-500/40 hover:scale-[1.02]',
        info: 'border-0 bg-gradient-to-br from-blue-500 via-blue-600 to-blue-700 text-white shadow-xl shadow-blue-500/30 hover:shadow-2xl hover:shadow-blue-500/40 hover:scale-[1.02]',
        success:
            'border-0 bg-gradient-to-br from-emerald-500 via-emerald-600 to-emerald-700 text-white shadow-xl shadow-emerald-500/30 hover:shadow-2xl hover:shadow-emerald-500/40 hover:scale-[1.02]',
    };

    return classes[tone];
}

function priorityIconClasses(tone: PriorityCard['tone']): string {
    const classes = {
        critical: 'bg-white/20 backdrop-blur-sm text-white',
        warning: 'bg-white/20 backdrop-blur-sm text-white',
        info: 'bg-white/20 backdrop-blur-sm text-white',
        success: 'bg-white/20 backdrop-blur-sm text-white',
    };

    return classes[tone];
}

// Animation classes
const entranceAnimationClass = computed(() =>
    prefersReducedMotion.value ? '' : 'animate-fade-in',
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="['space-y-8 p-6 lg:p-8', entranceAnimationClass]">
            <section
                class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]"
            >
                <Card
                    class="border-0 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white shadow-2xl shadow-blue-900/40 transition-all duration-300 hover:shadow-blue-900/60"
                >
                    <CardContent class="flex h-full flex-col gap-8 p-6 lg:p-8">
                        <div class="space-y-4">
                            <div
                                class="flex flex-wrap items-center gap-2 text-xs font-medium text-muted-foreground"
                            >
                                <span
                                    class="rounded-full bg-white/20 px-3 py-1 font-medium text-white backdrop-blur-sm"
                                >
                                    Pusat keputusan operasional
                                </span>
                                <span class="text-blue-100">{{
                                    todayLabel
                                }}</span>
                            </div>
                            <Heading
                                title="Dashboard operasional limbah"
                                description="Pantau risiko, keputusan yang menunggu tindakan, dan ritme operasional harian dalam satu tampilan."
                            />
                            <p
                                class="-mt-5 max-w-2xl text-sm leading-6 text-blue-100"
                            >
                                Fokus utama hari ini:
                                {{ dashboardStatus.description }}
                            </p>
                        </div>

                        <div
                            class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center"
                        >
                            <Button
                                as-child
                                class="bg-white text-blue-700 shadow-lg transition-all duration-200 hover:bg-blue-50 hover:shadow-xl"
                            >
                                <Link
                                    :href="
                                        wasteManagementRoutes.records.create()
                                            .url
                                    "
                                >
                                    Buat catatan limbah
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                as-child
                                class="border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-200 hover:border-white/50 hover:bg-white/20"
                            >
                                <Link
                                    :href="
                                        hasPendingApprovals
                                            ? wasteManagementRoutes.records.pendingApproval()
                                                  .url
                                            : wasteManagementRoutes.records.index()
                                                  .url
                                    "
                                >
                                    {{
                                        hasPendingApprovals
                                            ? 'Lihat persetujuan'
                                            : 'Pantau catatan'
                                    }}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white shadow-2xl shadow-slate-900/50 transition-all duration-300"
                >
                    <CardHeader class="space-y-3">
                        <div
                            class="inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-200"
                        >
                            <AlertTriangle class="size-3.5" />
                            {{ dashboardStatus.label }}
                        </div>
                        <CardTitle class="text-xl text-white">
                            Ringkasan keputusan hari ini
                        </CardTitle>
                        <CardDescription class="text-slate-300">
                            Lihat gambaran cepat sebelum masuk ke detail
                            operasional.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div
                                class="group rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm transition-all duration-300 hover:border-white/20 hover:bg-white/10 hover:shadow-lg"
                            >
                                <p class="text-sm font-medium text-slate-300">
                                    Total catatan limbah
                                </p>
                                <p
                                    class="mt-2 text-4xl font-bold text-white tabular-nums"
                                >
                                    {{ stats.total_waste_records }}
                                </p>
                                <p class="mt-2 text-xs text-slate-400">
                                    {{ stats.approved_records }} disetujui,
                                    {{ stats.pending_records }} menunggu proses
                                </p>
                            </div>
                            <div
                                class="group rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm transition-all duration-300 hover:border-white/20 hover:bg-white/10 hover:shadow-lg"
                            >
                                <p class="text-sm font-medium text-slate-300">
                                    Total transportasi
                                </p>
                                <p
                                    class="mt-2 text-4xl font-bold text-white tabular-nums"
                                >
                                    {{ stats.total_transportations }}
                                </p>
                                <p class="mt-2 text-xs text-slate-400">
                                    {{ stats.in_transit_transportations }} aktif
                                    dalam perjalanan
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="topCategory"
                            class="rounded-xl border border-white/10 bg-white/5 p-4 transition-all duration-200 hover:bg-white/10"
                        >
                            <p class="text-sm text-slate-300">
                                Kategori paling dominan
                            </p>
                            <div
                                class="mt-2 flex items-end justify-between gap-4"
                            >
                                <div>
                                    <p class="text-lg font-semibold text-white">
                                        {{ topCategory.category }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-400 tabular-nums"
                                    >
                                        {{ topCategory.count }} catatan saat ini
                                    </p>
                                </div>
                                <AlertCircle
                                    class="size-5 text-slate-400"
                                    aria-label="Category indicator icon"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section :class="['space-y-6', entranceAnimationClass]">
                <div class="space-y-1">
                    <p
                        class="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                    >
                        Prioritas hari ini
                    </p>
                    <h2 class="text-xl font-semibold tracking-tight">
                        Informasi yang perlu Anda putuskan lebih dulu
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Setiap kartu merangkum kondisi yang paling memengaruhi
                        operasional dan kepatuhan.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card
                        v-for="card in priorityCards"
                        :key="card.title"
                        :class="priorityCardClasses(card.tone)"
                        class="transition-shadow transition-transform duration-200 focus-within:ring-2 focus-within:ring-offset-2 hover:shadow-md focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                    >
                        <CardContent class="flex h-full flex-col gap-6 p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-sm font-semibold tracking-wide text-white/90 uppercase"
                                    >
                                        {{ card.title }}
                                    </p>
                                    <p
                                        class="mt-2 text-4xl font-bold text-white tabular-nums"
                                    >
                                        {{ card.value }}
                                    </p>
                                </div>
                                <div
                                    :class="priorityIconClasses(card.tone)"
                                    class="rounded-xl p-2"
                                >
                                    <component
                                        :is="card.icon"
                                        class="size-5"
                                        :aria-label="`${card.title} icon`"
                                    />
                                </div>
                            </div>
                            <p class="text-sm leading-6 text-white/80">
                                {{ card.description }}
                            </p>
                            <Button
                                variant="ghost"
                                as-child
                                class="w-fit px-0 text-white hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2"
                            >
                                <Link
                                    :href="card.href"
                                    class="text-white hover:text-white/90"
                                >
                                    {{ card.actionLabel }}
                                    <ArrowRight
                                        class="ml-2 size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section
                class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
            >
                <div class="space-y-6">
                    <Card
                        class="border border-slate-200/50 bg-white shadow-lg shadow-slate-200/50 transition-all duration-300 hover:shadow-xl hover:shadow-slate-300/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-900/50"
                    >
                        <CardHeader>
                            <div
                                class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                            >
                                <div>
                                    <CardTitle>Antrian tindakan</CardTitle>
                                    <CardDescription>
                                        Approve cepat, tolak dengan alasan yang
                                        jelas, atau buka detail untuk review
                                        lebih dalam.
                                    </CardDescription>
                                </div>
                                <Button
                                    v-if="hasPendingApprovals"
                                    size="default"
                                    variant="outline"
                                    class="min-h-[44px] focus-visible:ring-2 focus-visible:ring-offset-2"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            wasteManagementRoutes.records.pendingApproval()
                                                .url
                                        "
                                    >
                                        Buka semua persetujuan
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div
                                v-if="hasAlerts"
                                class="rounded-xl border border-orange-200 bg-orange-50 p-4 focus-within:ring-2 focus-within:ring-orange-600 focus-within:ring-offset-2 dark:border-orange-900/70 dark:bg-orange-950/30"
                            >
                                <div class="flex items-start gap-3">
                                    <AlertTriangle
                                        class="mt-0.5 size-5 text-orange-800 dark:text-orange-200"
                                        aria-label="Compliance risk alert icon"
                                    />
                                    <div class="space-y-1">
                                        <p class="font-medium text-foreground">
                                            Risiko kepatuhan perlu dipantau
                                        </p>
                                        <p
                                            class="text-sm leading-6 text-muted-foreground"
                                        >
                                            {{ stats.expired_waste }}
                                            catatan telah melewati batas simpan
                                            dan
                                            {{ stats.expiring_soon_waste }}
                                            catatan akan mendekati batas simpan
                                            dalam 7 hari.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <template v-if="hasPendingApprovals">
                                <Suspense>
                                    <template #default>
                                        <ApprovalList
                                            :approvals="pendingApprovals"
                                        />
                                    </template>
                                    <template #fallback>
                                        <SkeletonCard
                                            variant="default"
                                            :show-icon="true"
                                            :lines="3"
                                        />
                                    </template>
                                </Suspense>
                            </template>

                            <div
                                v-else
                                class="rounded-xl border border-dashed border-border bg-muted/30 p-5 transition-all duration-200 focus-within:ring-2 focus-within:ring-offset-2 hover:bg-muted/50"
                            >
                                <p class="font-medium text-foreground">
                                    Tidak ada keputusan yang tertunda
                                </p>
                                <p
                                    class="mt-1 text-sm leading-6 text-muted-foreground"
                                >
                                    Antrian persetujuan kosong. Gunakan ruang
                                    ini untuk memantau risiko kepatuhan dan
                                    ritme operasional tanpa interupsi proses
                                    approval.
                                </p>
                                <Button
                                    class="mt-4 min-h-[44px]"
                                    size="default"
                                    variant="outline"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            wasteManagementRoutes.records.index()
                                                .url
                                        "
                                    >
                                        Lihat seluruh catatan limbah
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="border border-slate-200/50 bg-white shadow-lg shadow-slate-200/50 transition-all duration-300 hover:shadow-xl hover:shadow-slate-300/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-900/50"
                    >
                        <CardHeader>
                            <CardTitle>Analisis kategori limbah</CardTitle>
                            <CardDescription>
                                Lihat kategori yang paling dominan untuk
                                membantu keputusan operasional berikutnya.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Suspense>
                                <template #default>
                                    <CategoryChart :data="wasteByCategory" />
                                </template>
                                <template #fallback>
                                    <SkeletonCard
                                        variant="compact"
                                        :show-icon="false"
                                        :lines="2"
                                    />
                                </template>
                            </Suspense>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card
                        class="border border-slate-200/50 bg-white shadow-lg shadow-slate-200/50 transition-all duration-300 hover:shadow-xl hover:shadow-slate-300/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-900/50"
                    >
                        <CardHeader>
                            <CardTitle>Visibilitas operasional</CardTitle>
                            <CardDescription>
                                Pantau pergerakan terbaru lalu buka catatan atau
                                transportasi terkait saat Anda perlu
                                menindaklanjuti.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Suspense>
                                <template #default>
                                    <ActivityFeed
                                        :activities="recentActivities"
                                    />
                                </template>
                                <template #fallback>
                                    <SkeletonCard
                                        variant="default"
                                        :show-icon="false"
                                        :lines="4"
                                    />
                                </template>
                            </Suspense>
                        </CardContent>
                    </Card>

                    <Card
                        class="border border-slate-200/50 bg-white shadow-lg shadow-slate-200/50 transition-all duration-300 hover:shadow-xl hover:shadow-slate-300/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-900/50"
                    >
                        <CardHeader>
                            <CardTitle>Status transportasi</CardTitle>
                            <CardDescription>
                                Ringkasan pengiriman aktif untuk membantu
                                koordinasi lapangan dan tindak lanjut.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Suspense>
                                <template #default>
                                    <StatusDistribution
                                        :data="transportationByStatus"
                                    />
                                </template>
                                <template #fallback>
                                    <SkeletonCard
                                        variant="compact"
                                        :show-icon="false"
                                        :lines="2"
                                    />
                                </template>
                            </Suspense>
                        </CardContent>
                    </Card>

                    <Card
                        class="border border-slate-200/50 bg-white shadow-lg shadow-slate-200/50 transition-all duration-300 hover:shadow-xl hover:shadow-slate-300/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-900/50"
                    >
                        <CardHeader>
                            <CardTitle>Aksi cepat</CardTitle>
                            <CardDescription>
                                Tindakan yang paling sering digunakan untuk
                                menjaga alur kerja tetap bergerak.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-4">
                            <Button
                                v-for="action in quickActions"
                                :key="action.title"
                                variant="outline"
                                class="h-auto justify-start px-4 py-4 text-left"
                                as-child
                            >
                                <Link :href="action.href" class="flex gap-3">
                                    <div
                                        class="mt-0.5 rounded-lg bg-muted p-2 text-foreground"
                                    >
                                        <component
                                            :is="action.icon"
                                            class="size-4"
                                            :aria-label="`${action.title} icon`"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="font-medium">
                                            {{ action.title }}
                                        </div>
                                        <div
                                            class="text-sm leading-6 text-muted-foreground"
                                        >
                                            {{ action.description }}
                                        </div>
                                    </div>
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}

/* Staggered animations for cards */
@media (prefers-reduced-motion: no-preference) {
    .grid > *:nth-child(1) {
        animation-delay: 0ms;
    }
    .grid > *:nth-child(2) {
        animation-delay: 50ms;
    }
    .grid > *:nth-child(3) {
        animation-delay: 100ms;
    }
    .grid > *:nth-child(4) {
        animation-delay: 150ms;
    }
}
</style>
