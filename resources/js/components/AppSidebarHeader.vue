<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, Shield, AlertTriangle } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, User } from '@/types';

const page = usePage();
const props = page.props;

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Clock state
const currentTime = ref('');
const currentDate = ref('');

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
    currentDate.value = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

let clockInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
    }
});

// Get header metadata from page props
const header = computed(() => props?.header as {
    organization_name: string;
    timezone: string;
    current_date: string;
    current_time: string;
    risk_status: 'normal' | 'warning' | 'critical';
    user: {
        name: string;
        email: string;
        role: string;
    };
} | undefined);

// Get notification summary
const notificationSummary = computed(() => props?.notificationSummary as {
    total_count: number;
    expired_waste_count: number;
    expiring_soon_waste_count: number;
    pending_waste_approvals_count: number;
    pending_faba_approvals_count: number;
    faba_warnings_count: number;
} | undefined);

// Get user from page props
const user = computed(() => props?.auth?.user as User | undefined);

// Risk status badge
const riskBadgeClass = computed(() => {
    if (!header.value) return '';

    switch (header.value.risk_status) {
        case 'critical':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'warning':
            return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
        default:
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    }
});

const riskBadgeLabel = computed(() => {
    if (!header.value) return 'Normal';

    switch (header.value.risk_status) {
        case 'critical':
            return 'Kritis';
        case 'warning':
            return 'Perlu Perhatian';
        default:
            return 'Normal';
    }
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <!-- Left side: Sidebar trigger and breadcrumbs -->
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Right side: Operational info -->
        <div class="flex items-center gap-3">
            <!-- Organization name & risk status -->
            <div class="hidden items-center gap-2 text-sm lg:flex">
                <div class="text-muted-foreground">
                    {{ header?.organization_name }}
                </div>
                <div class="h-4 w-px bg-border" />
                <div
                    :class="[
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        riskBadgeClass,
                    ]"
                >
                    <Shield class="mr-1 inline h-3 w-3" />
                    {{ riskBadgeLabel }}
                </div>
            </div>

            <!-- Live clock -->
            <div class="hidden flex-col items-end text-xs text-muted-foreground md:flex">
                <div class="font-medium tabular-nums text-foreground">
                    {{ currentTime }}
                </div>
                <div class="text-[10px]">
                    {{ currentDate }} · {{ header?.timezone ?? 'WIB' }}
                </div>
            </div>

            <!-- Notifications -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="relative">
                        <Bell class="h-4 w-4" />
                        <span
                            v-if="notificationSummary && notificationSummary.total_count > 0"
                            class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"
                        >
                            {{ notificationSummary.total_count > 99 ? '99+' : notificationSummary.total_count }}
                        </span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-80">
                    <DropdownMenuLabel class="font-normal">
                        <div class="flex items-center justify-between">
                            <span>Notifikasi</span>
                            <span
                                v-if="notificationSummary && notificationSummary.total_count > 0"
                                class="text-xs text-muted-foreground"
                            >
                                {{ notificationSummary.total_count }} aktif
                            </span>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />

                    <div v-if="notificationSummary" class="max-h-80 overflow-y-auto">
                        <!-- Expired waste -->
                        <div v-if="notificationSummary.expired_waste_count > 0" class="px-3 py-2">
                            <div class="flex items-start gap-2 text-sm">
                                <AlertTriangle class="h-4 w-4 text-red-500 mt-0.5" />
                                <div>
                                    <p class="font-medium text-red-600 dark:text-red-400">
                                        {{ notificationSummary.expired_waste_count }} limbah melewati batas simpan
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Segera tindak lanjuti catatan yang sudah melewati masa simpan aman.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Expiring soon -->
                        <div v-if="notificationSummary.expiring_soon_waste_count > 0" class="px-3 py-2">
                            <div class="flex items-start gap-2 text-sm">
                                <AlertTriangle class="h-4 w-4 text-orange-500 mt-0.5" />
                                <div>
                                    <p class="font-medium text-orange-600 dark:text-orange-400">
                                        {{ notificationSummary.expiring_soon_waste_count }} limbah mendekati batas simpan
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Catatan limbah akan mencapai batas simpan dalam 7 hari.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Pending approvals -->
                        <div v-if="notificationSummary.pending_waste_approvals_count > 0" class="px-3 py-2">
                            <div class="flex items-start gap-2 text-sm">
                                <Shield class="h-4 w-4 text-blue-500 mt-0.5" />
                                <div>
                                    <p class="font-medium text-blue-600 dark:text-blue-400">
                                        {{ notificationSummary.pending_waste_approvals_count }} approval limbah menunggu
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Terdapat catatan limbah yang belum direview.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- FABA approvals -->
                        <div v-if="notificationSummary.pending_faba_approvals_count > 0" class="px-3 py-2">
                            <div class="flex items-start gap-2 text-sm">
                                <Shield class="h-4 w-4 text-purple-500 mt-0.5" />
                                <div>
                                    <p class="font-medium text-purple-600 dark:text-purple-400">
                                        {{ notificationSummary.pending_faba_approvals_count }} approval FABA menunggu
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Rekap bulanan FABA menunggu keputusan.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- FABA warnings -->
                        <div v-if="notificationSummary.faba_warnings_count > 0" class="px-3 py-2">
                            <div class="flex items-start gap-2 text-sm">
                                <AlertTriangle class="h-4 w-4 text-red-500 mt-0.5" />
                                <div>
                                    <p class="font-medium text-red-600 dark:text-red-400">
                                        {{ notificationSummary.faba_warnings_count }} peringatan FABA
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Terdeteksi anomali seperti saldo negatif atau data belum konsisten.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- No notifications -->
                        <div v-if="notificationSummary && notificationSummary.total_count === 0" class="px-3 py-4 text-center text-sm text-muted-foreground">
                            <p>Tidak ada notifikasi aktif</p>
                        </div>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- User menu -->
            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" class="flex items-center gap-3 rounded-full px-2">
                        <div class="hidden md:flex flex-col items-end text-xs text-left">
                            <span class="font-medium">{{ user.name }}</span>
                            <span class="text-[10px] text-muted-foreground">
                                {{ header?.user?.role ?? 'User' }}
                            </span>
                        </div>
                        <Avatar class="size-9 border border-border bg-muted">
                            <AvatarFallback class="bg-muted text-xs font-semibold text-foreground">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <ChevronDown class="h-4 w-4 text-muted-foreground" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-56" align="end">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
