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
    '#1d4ed8',
    '#0f766e',
    '#d97706',
    '#475569',
    '#0891b2',
    '#dc2626',
];

const totalValue = computed(() =>
    props.data.reduce((sum, item) => sum + item.value, 0),
);

const isEmpty = computed(
    () => totalValue.value <= 0 || props.data.length === 0,
);

const segments = computed(() => {
    let start = 0;

    return props.data.map((item, index) => {
        const color = chartColors[index % chartColors.length];
        const normalizedPercentage =
            totalValue.value > 0 ? (item.value / totalValue.value) * 100 : 0;
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
            .map(
                (segment) =>
                    `${segment.color} ${segment.start}% ${segment.end}%`,
            )
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
        <div
            class="grid gap-4 lg:grid-cols-[156px_minmax(0,1fr)] lg:items-center"
        >
            <div class="flex justify-center">
                <div
                    class="wm-panel-elevated relative flex h-32 w-32 items-center justify-center rounded-full border"
                    :style="donutStyle"
                >
                    <div
                        class="absolute inset-2.5 rounded-full border border-white/70 dark:border-slate-700/70"
                    />
                    <div
                        class="wm-panel flex h-20 w-20 flex-col items-center justify-center rounded-full border text-center"
                    >
                        <span
                            class="wm-text-muted text-[10px] font-semibold tracking-[0.12em] uppercase"
                        >
                            {{ totalLabel }}
                        </span>
                        <span
                            class="wm-text-primary mt-1 text-xl font-bold tabular-nums"
                        >
                            {{ isEmpty ? emptyLabel : formatValue(totalValue) }}
                        </span>
                        <span class="wm-text-muted text-xs">
                            {{ valueSuffix || '' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="space-y-2.5">
                <div
                    v-if="isEmpty"
                    class="wm-panel rounded-lg border border-dashed px-4 py-6 text-center text-sm"
                >
                    Belum ada komposisi yang dapat ditampilkan pada snapshot
                    ini.
                </div>
                <div
                    v-for="(item, index) in segments"
                    :key="item.label"
                    class="wm-panel flex items-center gap-3 rounded-xl border px-3.5 py-3 transition-colors duration-200 hover:bg-white dark:hover:bg-slate-900"
                >
                    <div
                        class="wm-text-muted w-5 text-[11px] font-semibold tabular-nums"
                    >
                        {{ index + 1 }}
                    </div>
                    <div
                        class="h-3.5 w-3.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: item.color }"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="wm-text-primary truncate text-sm font-medium">
                            {{ item.label }}
                        </p>
                        <p class="wm-text-secondary text-xs">
                            {{ formatValue(item.value) }} {{ valueSuffix }} •
                            {{ item.normalizedPercentage.toFixed(1) }}%
                        </p>
                    </div>
                    <div class="text-right">
                        <p
                            class="wm-text-primary text-sm font-semibold tabular-nums"
                        >
                            {{ item.normalizedPercentage.toFixed(1) }}%
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
