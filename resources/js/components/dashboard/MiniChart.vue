<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

interface DataPoint {
    label: string;
    value: number;
    color?: string;
}

interface Props {
    title: string;
    data: DataPoint[];
    maxBars?: number;
}

const props = withDefaults(defineProps<Props>(), {
    maxBars: 5,
});

const fallbackColors = [
    'bg-emerald-500',
    'bg-blue-500',
    'bg-orange-500',
    'bg-violet-500',
    'bg-amber-500',
];

const displayData = computed(() => props.data.slice(0, props.maxBars));

const maxValue = computed(() =>
    Math.max(...displayData.value.map((d) => d.value), 1),
);
</script>

<template>
    <Card class="border-slate-200/80 shadow-sm">
        <CardContent class="p-4">
            <p class="mb-4 text-sm font-medium text-foreground">{{ title }}</p>
            <div class="space-y-3">
                <div
                    v-for="(item, index) in displayData"
                    :key="item.label"
                    class="rounded-xl border border-slate-100 bg-slate-50/70 p-3"
                >
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <p class="truncate text-xs font-medium text-slate-600">
                            {{ item.label }}
                        </p>
                        <p
                            class="text-sm font-semibold text-slate-900 tabular-nums"
                        >
                            {{ item.value.toLocaleString('id-ID') }}
                        </p>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-200">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="
                                item.color ||
                                fallbackColors[index % fallbackColors.length]
                            "
                            :style="{
                                width: `${(item.value / maxValue) * 100}%`,
                            }"
                        />
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
