<script setup lang="ts">
import { computed } from 'vue';

interface DistributionItem {
    label: string;
    value: number;
    percentage: number;
}

interface Props {
    data: DistributionItem[];
    totalLabel?: string;
    valueSuffix?: string;
    emptyLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    totalLabel: 'Total',
    valueSuffix: '',
    emptyLabel: '-',
});

const chartColors = [
    '#2563eb',
    '#10b981',
    '#f97316',
    '#8b5cf6',
    '#eab308',
    '#ef4444',
];

const totalValue = computed(() =>
    props.data.reduce((sum, item) => sum + item.value, 0),
);

const isEmpty = computed(() => totalValue.value <= 0 || props.data.length === 0);

const segments = computed(() => {
    let start = 0;

    return props.data.map((item, index) => {
        const color = chartColors[index % chartColors.length];
        const normalizedPercentage = totalValue.value > 0
            ? (item.value / totalValue.value) * 100
            : 0;
        const end = start + normalizedPercentage;
        const segment = {
            ...item,
            color,
            normalizedPercentage,
            start,
            end,
        };

        start = end;

        return segment;
    });
});

const donutStyle = computed(() => {
    if (isEmpty.value) {
        return {
            background: 'conic-gradient(#e5e7eb 0 100%)',
        };
    }

    return {
        background: `conic-gradient(${segments.value
            .map((segment) => `${segment.color} ${segment.start}% ${segment.end}%`)
            .join(', ')})`,
    };
});

function formatValue(value: number): string {
    if (Number.isInteger(value)) {
        return value.toLocaleString('id-ID');
    }

    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-center">
            <div class="flex justify-center">
                <div
                    class="relative flex h-52 w-52 items-center justify-center rounded-full border border-white/90 shadow-[0_22px_45px_-28px_rgba(15,23,42,0.35)] dark:border-slate-800/80 dark:shadow-[0_22px_45px_-28px_rgba(2,6,23,0.9)]"
                    :style="donutStyle"
                >
                    <div class="absolute inset-3 rounded-full border border-white/40 dark:border-slate-700/70" />
                    <div class="flex h-30 w-30 flex-col items-center justify-center rounded-full border border-white/90 bg-white/95 text-center shadow-sm backdrop-blur dark:border-slate-800/80 dark:bg-slate-950/92 dark:shadow-none">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground dark:text-slate-400">
                            {{ totalLabel }}
                        </span>
                        <span class="mt-1 text-3xl font-semibold text-foreground tabular-nums">
                            {{ isEmpty ? emptyLabel : formatValue(totalValue) }}
                        </span>
                        <span class="text-xs text-muted-foreground dark:text-slate-400">
                            {{ valueSuffix || '' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-if="isEmpty"
                    class="rounded-[20px] border border-dashed border-slate-200 bg-white/80 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-400"
                >
                    Belum ada komposisi yang dapat ditampilkan pada snapshot ini.
                </div>
                <div
                    v-for="item in segments"
                    :key="item.label"
                    class="flex items-center gap-3 rounded-[20px] border border-white/90 bg-white/85 px-3.5 py-3 shadow-sm shadow-slate-100/70 transition-all duration-200 hover:-translate-y-0.5 hover:bg-white dark:border-slate-800/80 dark:bg-slate-950/78 dark:shadow-none dark:hover:bg-slate-900/90"
                >
                    <div
                        class="h-3.5 w-3.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: item.color }"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ item.label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatValue(item.value) }} {{ valueSuffix }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold tabular-nums text-foreground">
                            {{ item.normalizedPercentage.toFixed(1) }}%
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
