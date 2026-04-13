<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    ClipboardList,
    Clock3,
    FilePenLine,
    ShieldAlert,
    Truck,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface DashboardTaskItem {
    id: string;
    type: 'waste_record' | 'faba_approval' | 'waste_hauling';
    task_group: 'approval' | 'revision' | 'follow_up';
    title: string;
    subtitle: string;
    status: string;
    priority: 'danger' | 'warning' | 'success' | 'info' | 'neutral';
    age_label: string;
    href: string;
}

type TabType = 'waste' | 'faba' | 'hauling_attention';

interface Props {
    wasteTasks: DashboardTaskItem[];
    fabaTasks: DashboardTaskItem[];
    haulingAttentionTasks: DashboardTaskItem[];
    taskContext: 'operator' | 'approver';
    wastePendingCount: number;
    fabaPendingCount: number;
    haulingAttentionCount: number;
}

const props = defineProps<Props>();

const activeTab = ref<TabType>(
    props.haulingAttentionCount >= props.wastePendingCount &&
        props.haulingAttentionCount >= props.fabaPendingCount
        ? 'hauling_attention'
        : props.wastePendingCount >= props.fabaPendingCount
          ? 'waste'
          : 'faba',
);

const panelTitle = computed(() =>
    props.taskContext === 'operator'
        ? 'Task Perlu Tindak Lanjut'
        : 'Task Perlu Keputusan',
);

const activeTasks = computed(() => {
    if (activeTab.value === 'waste') {
        return props.wasteTasks;
    }

    if (activeTab.value === 'faba') {
        return props.fabaTasks;
    }

    return props.haulingAttentionTasks;
});

const activePendingCount = computed(() => {
    if (activeTab.value === 'waste') {
        return props.wastePendingCount;
    }

    if (activeTab.value === 'faba') {
        return props.fabaPendingCount;
    }

    return props.haulingAttentionCount;
});

const viewAllHref = computed(() => {
    if (activeTab.value === 'waste') {
        return props.taskContext === 'operator'
            ? '/waste-management/records?status=pending_review,rejected'
            : '/waste-management/records?status=pending_review';
    }

    if (activeTab.value === 'hauling_attention') {
        return '/waste-management/haulings';
    }

    return props.taskContext === 'operator'
        ? '/waste-management/faba/approvals?status=submitted,rejected'
        : '/waste-management/faba/approvals?status=submitted';
});

function statusBadgeClass(priority: DashboardTaskItem['priority']): string {
    switch (priority) {
        case 'danger':
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200';
        case 'warning':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200';
        case 'success':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200';
        case 'info':
            return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-200';
        default:
            return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200';
    }
}

function leadingIcon(task: DashboardTaskItem): LucideIcon {
    if (task.task_group === 'revision') {
        return FilePenLine;
    }

    if (task.type === 'faba_approval') {
        return ShieldAlert;
    }

    if (task.type === 'waste_hauling') {
        return Truck;
    }

    if (task.task_group === 'approval') {
        return ClipboardList;
    }

    return ShieldAlert;
}
</script>

<template>
    <Card
        class="wm-panel-work flex h-full min-h-[520px] flex-col overflow-hidden border shadow-sm xl:min-h-0"
    >
        <CardHeader
            class="wm-border-strong shrink-0 border-b bg-slate-50/40 px-3 py-2 dark:bg-slate-900/30"
        >
            <div class="space-y-0.5">
                <p
                    class="wm-text-muted text-[10px] font-semibold tracking-[0.12em] uppercase"
                >
                    Task List
                </p>
                <CardTitle class="wm-text-primary text-sm">
                    {{ panelTitle }}
                </CardTitle>
            </div>

            <!-- Tab Navigation -->
            <div class="mt-3">
                <div
                    class="grid grid-cols-3 gap-0 border-b border-slate-200 dark:border-slate-700"
                >
                    <button
                        :class="[
                            'relative px-3 py-2 text-left transition-all duration-200',
                            activeTab === 'waste'
                                ? ''
                                : 'hover:bg-slate-50/50 dark:hover:bg-slate-900/30',
                        ]"
                        @click="activeTab = 'waste'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                :class="[
                                    'text-xs font-medium',
                                    activeTab === 'waste'
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'wm-text-muted',
                                ]"
                            >
                                Limbah
                            </span>
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                    activeTab === 'waste'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
                                        : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                                ]"
                            >
                                {{ wastePendingCount }}
                            </span>
                        </div>
                        <div
                            v-if="activeTab === 'waste'"
                            class="absolute right-0 bottom-0 left-0 h-0.5 bg-emerald-500 dark:bg-emerald-400"
                        />
                    </button>

                    <button
                        :class="[
                            'relative px-3 py-2 text-left transition-all duration-200',
                            activeTab === 'faba'
                                ? ''
                                : 'hover:bg-slate-50/50 dark:hover:bg-slate-900/30',
                        ]"
                        @click="activeTab = 'faba'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                :class="[
                                    'text-xs font-medium',
                                    activeTab === 'faba'
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'wm-text-muted',
                                ]"
                            >
                                FABA
                            </span>
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                    activeTab === 'faba'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
                                        : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                                ]"
                            >
                                {{ fabaPendingCount }}
                            </span>
                        </div>
                        <div
                            v-if="activeTab === 'faba'"
                            class="absolute right-0 bottom-0 left-0 h-0.5 bg-emerald-500 dark:bg-emerald-400"
                        />
                    </button>

                    <button
                        :class="[
                            'relative px-3 py-2 text-left transition-all duration-200',
                            activeTab === 'hauling_attention'
                                ? ''
                                : 'hover:bg-slate-50/50 dark:hover:bg-slate-900/30',
                        ]"
                        @click="activeTab = 'hauling_attention'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                :class="[
                                    'text-xs font-medium',
                                    activeTab === 'hauling_attention'
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'wm-text-muted',
                                ]"
                            >
                                Atensi
                            </span>
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                    activeTab === 'hauling_attention'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
                                        : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                                ]"
                            >
                                {{ haulingAttentionCount }}
                            </span>
                        </div>
                        <div
                            v-if="activeTab === 'hauling_attention'"
                            class="absolute right-0 bottom-0 left-0 h-0.5 bg-emerald-500 dark:bg-emerald-400"
                        />
                    </button>
                </div>
            </div>
        </CardHeader>

        <CardContent class="flex min-h-0 flex-1 flex-col overflow-hidden p-2.5">
            <div
                v-if="activeTasks.length === 0"
                class="wm-panel-elevated flex flex-1 items-center justify-center rounded-lg border border-dashed p-4 text-center"
            >
                <div class="space-y-1.5">
                    <CheckCircle2
                        class="mx-auto size-4 text-emerald-500 dark:text-emerald-300"
                    />
                    <p class="wm-text-primary text-xs font-medium">
                        Tidak ada task aktif
                    </p>
                </div>
            </div>

            <div v-else class="flex min-h-0 flex-1 flex-col space-y-3">
                <!-- Pending Count Badge -->
                <div class="flex shrink-0 items-center justify-between px-1">
                    <p class="wm-text-secondary text-[10px] font-medium">
                        {{
                            activeTab === 'waste'
                                ? 'Limbah'
                                : activeTab === 'faba'
                                  ? 'FABA'
                                  : 'Limbah atensi'
                        }}
                        yang perlu ditangani
                    </p>
                    <span
                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ activePendingCount }} items
                    </span>
                </div>

                <!-- Scrollable List -->
                <div class="flex h-0 min-h-0 flex-1 overflow-y-auto pr-1">
                    <div class="flex w-full flex-col space-y-2">
                        <Link
                            v-for="task in activeTasks"
                            :key="task.id"
                            :href="task.href"
                            class="wm-panel-elevated block rounded-lg border px-2.5 py-2 transition-colors duration-200 hover:border-slate-300 hover:bg-white dark:hover:border-slate-700 dark:hover:bg-slate-950"
                        >
                            <div class="flex items-start gap-2">
                                <div
                                    class="wm-surface-subtle flex size-7 shrink-0 items-center justify-center rounded-md border"
                                >
                                    <component
                                        :is="leadingIcon(task)"
                                        class="size-3 text-slate-700 dark:text-slate-200"
                                    />
                                </div>

                                <div class="min-w-0 flex-1 space-y-1">
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="wm-text-primary truncate text-xs font-semibold"
                                            >
                                                {{ task.title }}
                                            </p>
                                            <p
                                                class="wm-text-secondary mt-0.5 line-clamp-2 text-[10px] leading-4"
                                            >
                                                {{ task.subtitle }}
                                            </p>
                                        </div>

                                        <ArrowRight
                                            class="mt-0.5 size-3 shrink-0 text-slate-400 dark:text-slate-500"
                                        />
                                    </div>

                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <Badge
                                            variant="secondary"
                                            :class="[
                                                'rounded-md border px-1 py-0.5 text-[9px] font-medium',
                                                statusBadgeClass(task.priority),
                                            ]"
                                        >
                                            {{ task.status }}
                                        </Badge>
                                        <span
                                            class="wm-text-muted inline-flex items-center gap-0.5 text-[9px] font-medium"
                                        >
                                            <Clock3 class="size-2.5" />
                                            {{ task.age_label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- View All Link -->
                <div class="shrink-0 pt-1">
                    <Link
                        :href="viewAllHref"
                        class="flex items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[10px] font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    >
                        Lihat Semua
                        <ArrowRight class="size-3" />
                    </Link>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
