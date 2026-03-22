<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ShieldAlert, Timer } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import wasteManagementRoutes from '@/routes/waste-management';

interface Props {
    expiredWaste: number;
    expiringSoonWaste: number;
    pendingWasteApprovals: number;
    pendingFabaApprovals: number;
    fabaWarnings: number;
}

const props = defineProps<Props>();

const hasAlerts = computed(() => props.expiredWaste > 0);
const hasWarnings = computed(
    () =>
        props.expiringSoonWaste > 0 ||
        props.pendingWasteApprovals > 0 ||
        props.pendingFabaApprovals > 0 ||
        props.fabaWarnings > 0,
);

const alertCount = computed(() => props.expiredWaste);
const warningCount = computed(
    () =>
        props.expiringSoonWaste +
        props.pendingWasteApprovals +
        props.pendingFabaApprovals +
        props.fabaWarnings,
);

const statusLabel = computed(() => {
    if (hasAlerts.value) return 'Kritis';
    if (hasWarnings.value) return 'Perlu Perhatian';
    return 'Ter kendali';
});

const statusColor = computed(() => {
    if (hasAlerts.value) return 'from-red-500 via-red-600 to-red-700';
    if (hasWarnings.value) return 'from-orange-400 via-orange-500 to-orange-600';
    return 'from-emerald-500 via-emerald-600 to-emerald-700';
});
</script>

<template>
    <div
        :class="[
            'border-0 bg-gradient-to-br text-white shadow-xl transition-all duration-300',
            statusColor,
        ]"
    >
        <div class="flex flex-col gap-6 p-6 lg:p-8">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div
                        class="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-medium backdrop-blur-sm"
                    >
                        <ShieldAlert class="size-3.5" />
                        Status Kepatuhan: {{ statusLabel }}
                    </div>
                    <h2 class="text-xl font-bold lg:text-2xl">
                        Tinjau Risiko Kepatuhan
                    </h2>
                </div>
                <div
                    class="rounded-full bg-white/20 px-4 py-2 text-center backdrop-blur-sm"
                >
                    <p class="text-xs font-medium text-white/80">
                        {{ alertCount }} Risiko
                    </p>
                    <p class="text-2xl font-bold">{{ warningCount }}</p>
                </div>
            </div>

            <!-- Alert Grid -->
            <div
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <!-- Expired Waste -->
                <div
                    :class="[
                        'rounded-xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm transition-all duration-200 hover:bg-white/20',
                        expiredWaste > 0 ? 'ring-2 ring-white/50' : '',
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <ShieldAlert class="size-4" />
                        <p class="text-sm font-medium text-white/90">
                            Melewati Batas Simpan
                        </p>
                    </div>
                    <p class="mt-2 text-3xl font-bold">{{ expiredWaste }}</p>
                    <p class="text-xs text-white/70">Catatan perlu tindakan</p>
                </div>

                <!-- Expiring Soon -->
                <div
                    :class="[
                        'rounded-xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm transition-all duration-200 hover:bg-white/20',
                        expiringSoonWaste > 0 ? 'ring-2 ring-white/30' : '',
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <Timer class="size-4" />
                        <p class="text-sm font-medium text-white/90">
                            Mendekati Batas Simpan
                        </p>
                    </div>
                    <p class="mt-2 text-3xl font-bold">{{ expiringSoonWaste }}</p>
                    <p class="text-xs text-white/70">Dalam 7 hari</p>
                </div>

                <!-- Pending Approvals -->
                <div
                    :class="[
                        'rounded-xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm transition-all duration-200 hover:bg-white/20',
                        pendingWasteApprovals > 0 || pendingFabaApprovals > 0
                            ? 'ring-2 ring-white/30'
                            : '',
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4" />
                        <p class="text-sm font-medium text-white/90">
                            Pending Approval
                        </p>
                    </div>
                    <p class="mt-2 text-3xl font-bold">
                        {{ pendingWasteApprovals + pendingFabaApprovals }}
                    </p>
                    <p class="text-xs text-white/70">
                        {{ pendingWasteApprovals }} limbah,
                        {{ pendingFabaApprovals }} FABA
                    </p>
                </div>

                <!-- FABA Warnings -->
                <div
                    :class="[
                        'rounded-xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm transition-all duration-200 hover:bg-white/20',
                        fabaWarnings > 0 ? 'ring-2 ring-white/30' : '',
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4" />
                        <p class="text-sm font-medium text-white/90">
                            Peringatan FABA
                        </p>
                    </div>
                    <p class="mt-2 text-3xl font-bold">{{ fabaWarnings }}</p>
                    <p class="text-xs text-white/70">Periode bermasalah</p>
                </div>
            </div>

            <!-- Action Button -->
            <div>
                <Button
                    as-child
                    class="bg-white text-red-700 shadow-lg transition-all duration-200 hover:bg-red-50"
                >
                    <Link
                        :href="
                            expiredWaste > 0
                                ? wasteManagementRoutes.records.index().url
                                : wasteManagementRoutes.records.pendingApproval()
                                      .url
                        "
                    >
                        {{
                            hasAlerts
                                ? 'Tinjau Catatan Kritis'
                                : 'Lihat Antrian Approval'
                        }}
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
