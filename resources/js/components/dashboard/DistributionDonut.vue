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
                    class="relative flex h-48 w-48 items-center justify-center rounded-full"
                    :style="donutStyle"
                >
                    <div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-background text-center shadow-sm">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ totalLabel }}
                        </span>
                        <span class="mt-1 text-3xl font-semibold text-foreground tabular-nums">
                            {{ isEmpty ? emptyLabel : formatValue(totalValue) }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ valueSuffix || '' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="item in segments"
                    :key="item.label"
                    class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5"
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
