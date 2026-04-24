<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { WasteChartData } from '@/types';

interface Props {
    data: WasteChartData[];
}

const props = defineProps<Props>();

const maxValue = computed(() => {
    const maxInput = Math.max(
        ...props.data.map((d) => d.approved_input_count),
        0,
    );
    const maxCompleted = Math.max(
        ...props.data.map((d) => d.completed_count),
        0,
    );
    const maxBacklog = Math.max(
        ...props.data.map((d) => d.closing_backlog_count),
        0,
    );

    return Math.max(maxInput, maxCompleted, maxBacklog, 1);
});

const recordsBarColor = 'rgb(34, 197, 94, 0.8)';
const hauledBarColor = 'rgb(249, 115, 22, 0.8)';
const backlogBarColor = 'rgb(37, 99, 235, 0.8)';

const totalRecords = computed(() =>
    props.data.reduce((sum, item) => sum + item.approved_input_count, 0),
);

const totalTransported = computed(() =>
    props.data.reduce((sum, item) => sum + item.completed_count, 0),
);

const totalBacklog = computed(() =>
    props.data.reduce((sum, item) => sum + item.closing_backlog_count, 0),
);
</script>

<template>
    <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="space-y-4 pb-4">
            <div>
                <CardTitle class="text-base sm:text-lg">
                    Pencatatan vs Pengangkutan Limbah
                </CardTitle>
                <CardDescription>
                    Perbandingan volume pencatatan limbah dan pengangkutan
                    yang disetujui untuk membaca throughput operasional 6 bulan
                    terakhir.
                </CardDescription>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div
                    class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3"
                >
                    <p
                        class="text-[11px] font-semibold tracking-wide text-emerald-700 uppercase"
                    >
                        Total catatan
                    </p>
                    <p class="mt-1 text-lg font-semibold text-emerald-900">
                        {{ totalRecords.toLocaleString('id-ID') }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-blue-100 bg-blue-50/70 p-3"
                >
                    <p
                        class="text-[11px] font-semibold tracking-wide text-blue-700 uppercase"
                    >
                        Total backlog
                    </p>
                    <p class="mt-1 text-lg font-semibold text-blue-900">
                        {{ totalBacklog.toLocaleString('id-ID') }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-orange-100 bg-orange-50/70 p-3"
                >
                    <p
                        class="text-[11px] font-semibold tracking-wide text-orange-700 uppercase"
                    >
                        Total diangkut
                    </p>
                    <p class="mt-1 text-lg font-semibold text-orange-900">
                        {{ totalTransported.toLocaleString('id-ID') }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <div
                v-for="(item, index) in data"
                :key="index"
                class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-slate-900">
                        {{ item.label }}
                    </span>
                    <span
                        class="rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 shadow-sm"
                    >
                        {{ item.closing_backlog_count }} backlog
                    </span>
                </div>

                <div class="space-y-2">
                    <div class="space-y-1">
                        <div
                            class="flex items-center justify-between text-[11px] font-medium tracking-wide text-emerald-700 uppercase"
                        >
                            <span>Pencatatan limbah</span>
                            <span>{{
                                item.approved_input_count.toLocaleString(
                                    'id-ID',
                                )
                            }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex-1 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :style="{
                                        width: `${(item.approved_input_count / maxValue) * 100}%`,
                                        backgroundColor: recordsBarColor,
                                    }"
                                />
                            </div>
                            <span
                                class="w-16 text-right text-xs font-medium text-slate-700"
                            >
                                {{ item.approved_input_count }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div
                            class="flex items-center justify-between text-[11px] font-medium tracking-wide text-orange-700 uppercase"
                        >
                            <span>Pengangkutan disetujui</span>
                            <span>{{
                                item.completed_count.toLocaleString('id-ID')
                            }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex-1 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :style="{
                                        width: `${(item.completed_count / maxValue) * 100}%`,
                                        backgroundColor: hauledBarColor,
                                    }"
                                />
                            </div>
                            <span
                                class="w-16 text-right text-xs font-medium text-slate-700"
                            >
                                {{ item.completed_count }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div
                            class="flex items-center justify-between text-[11px] font-medium tracking-wide text-blue-700 uppercase"
                        >
                            <span>Backlog akhir</span>
                            <span>{{
                                item.closing_backlog_count.toLocaleString(
                                    'id-ID',
                                )
                            }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex-1 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :style="{
                                        width: `${(item.closing_backlog_count / maxValue) * 100}%`,
                                        backgroundColor: backlogBarColor,
                                    }"
                                />
                            </div>
                            <span
                                class="w-16 text-right text-xs font-medium text-slate-700"
                            >
                                {{ item.closing_backlog_count }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center gap-6 pt-2 text-xs">
                <div class="flex items-center gap-2">
                    <div
                        class="h-2 w-3 rounded-full"
                        :style="{ backgroundColor: recordsBarColor }"
                    />
                    <span class="text-muted-foreground">Catatan limbah</span>
                </div>
                <div class="flex items-center gap-2">
                    <div
                        class="h-2 w-3 rounded-full"
                        :style="{ backgroundColor: hauledBarColor }"
                    />
                    <span class="text-muted-foreground"
                        >Pengangkutan disetujui</span
                    >
                </div>
                <div class="flex items-center gap-2">
                    <div
                        class="h-2 w-3 rounded-full"
                        :style="{ backgroundColor: backlogBarColor }"
                    />
                    <span class="text-muted-foreground">Backlog akhir</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
