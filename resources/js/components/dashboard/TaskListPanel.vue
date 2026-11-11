<script setup lang="ts">
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
import Link from '@/components/OrganizationLink.vue';
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
        ? 'Prioritas tindak lanjut'
        : 'Antrean keputusan',
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
const tabs = computed<Array<{ value: TabType; label: string; count: number }>>(
    () => [
        { value: 'waste', label: 'Limbah', count: props.wastePendingCount },
        { value: 'faba', label: 'FABA', count: props.fabaPendingCount },
        {
            value: 'hauling_attention',
            label: 'Atensi',
            count: props.haulingAttentionCount,
        },
    ],
);
</script>

<template>
    <Card
        class="min-w-0 gap-0 overflow-hidden rounded-2xl border-slate-200 bg-white py-0 shadow-none dark:border-slate-800 dark:bg-slate-900"
    >
        <CardHeader class="gap-4 px-5 pt-5 pb-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p
                        class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Ruang kerja
                    </p>
                    <CardTitle class="text-base font-semibold">{{
                        panelTitle
                    }}</CardTitle>
                </div>
                <ClipboardList
                    class="size-5 text-slate-400"
                    aria-hidden="true"
                />
            </div>
            <div
                class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-950"
                role="group"
                aria-label="Jenis tindak lanjut"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    :aria-pressed="activeTab === tab.value"
                    class="flex min-h-11 items-center justify-center gap-2 rounded-lg px-2 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    :class="
                        activeTab === tab.value
                            ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-800 dark:text-blue-300'
                            : 'text-slate-600 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white'
                    "
                    @click="activeTab = tab.value"
                >
                    {{ tab.label
                    }}<span
                        class="rounded-md bg-slate-100 px-1.5 py-0.5 tabular-nums dark:bg-slate-900"
                        >{{ tab.count }}</span
                    >
                </button>
            </div>
        </CardHeader>
        <CardContent class="px-5 pb-5">
            <div
                class="mb-3 flex items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400"
                aria-live="polite"
            >
                <span>{{ activePendingCount }} item perlu ditangani</span
                ><span v-if="activeTasks.length > 0"
                    >{{ activeTasks.length }} ditampilkan</span
                >
            </div>
            <div
                v-if="activeTasks.length === 0"
                class="flex min-h-52 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-slate-200 px-5 py-8 text-center dark:border-slate-700"
            >
                <CheckCircle2
                    class="size-7 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                />
                <p
                    class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                >
                    {{
                        activePendingCount > 0
                            ? 'Rincian tugas belum tersedia'
                            : 'Tidak ada tugas pada kategori ini'
                    }}
                </p>
                <p
                    class="max-w-56 text-xs leading-5 text-slate-500 dark:text-slate-400"
                >
                    {{
                        activePendingCount > 0
                            ? 'Buka daftar lengkap untuk memeriksa item yang perlu ditangani.'
                            : 'Pilih kategori lain untuk melihat tindak lanjut operasional.'
                    }}
                </p>
            </div>
            <ul
                v-else
                class="max-h-[440px] space-y-2 overflow-y-auto pr-1"
                aria-label="Daftar tindak lanjut"
            >
                <li v-for="task in activeTasks" :key="task.id">
                    <Link
                        :href="task.href"
                        class="group block rounded-xl border border-slate-200 p-3 transition-colors hover:border-blue-300 hover:bg-blue-50/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-slate-800 dark:hover:border-blue-700 dark:hover:bg-blue-950/30"
                        ><div class="flex items-start gap-3">
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                                ><component
                                    :is="leadingIcon(task)"
                                    class="size-4"
                                    aria-hidden="true"
                            /></span>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm leading-5 font-semibold break-words text-slate-900 dark:text-slate-100"
                                >
                                    {{ task.title }}
                                </p>
                                <p
                                    class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ task.subtitle }}
                                </p>
                                <div
                                    class="mt-3 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="rounded-md border px-2 py-0.5 text-[11px] font-medium"
                                        :class="statusBadgeClass(task.priority)"
                                        >{{ task.status }}</span
                                    ><span
                                        class="inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400"
                                        ><Clock3
                                            class="size-3"
                                            aria-hidden="true"
                                        />{{ task.age_label }}</span
                                    >
                                </div>
                            </div>
                            <ArrowRight
                                class="mt-1 size-4 shrink-0 text-slate-400 group-hover:text-blue-600"
                                aria-hidden="true"
                            /></div
                    ></Link>
                </li>
            </ul>
            <Link
                :href="viewAllHref"
                class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                >Buka daftar lengkap<ArrowRight
                    class="size-4"
                    aria-hidden="true"
            /></Link>
        </CardContent>
    </Card>
</template>
