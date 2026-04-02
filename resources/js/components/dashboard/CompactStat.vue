<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

interface Props {
    title: string;
    value: string | number;
    unit?: string;
    href?: string;
    contextLabel?: string;
    hint?: string;
    color?: 'blue' | 'emerald' | 'orange' | 'red';
}

const props = withDefaults(defineProps<Props>(), {
    unit: '',
    color: 'blue',
});

const accentClasses = computed(() => {
    const colors = {
        blue: {
            badge: 'bg-blue-50 text-blue-600',
            chip: 'bg-blue-50 text-blue-700',
        },
        emerald: {
            badge: 'bg-emerald-50 text-emerald-600',
            chip: 'bg-emerald-50 text-emerald-700',
        },
        orange: {
            badge: 'bg-orange-50 text-orange-600',
            chip: 'bg-orange-50 text-orange-700',
        },
        red: {
            badge: 'bg-red-50 text-red-600',
            chip: 'bg-red-50 text-red-700',
        },
    };

    return colors[props.color];
});
</script>

<template>
    <Card class="rounded-2xl border border-slate-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md">
        <CardContent class="p-5">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div
                    :class="[
                        'rounded-xl p-2.5',
                        accentClasses.badge,
                    ]"
                >
                    <div class="h-2.5 w-2.5 rounded-full bg-current" />
                </div>
                <span
                    :class="[
                        'rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                        accentClasses.chip,
                    ]"
                >
                    {{ contextLabel || unit || 'Status' }}
                </span>
            </div>

            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {{ title }}
            </p>
            <p class="mt-2 text-3xl font-black tabular-nums text-slate-900">
                {{ value }}
                <span v-if="unit" class="ml-1 text-sm font-medium text-slate-400">
                    {{ unit }}
                </span>
            </p>
            <p
                v-if="hint"
                class="mt-2 text-xs leading-5 text-slate-500"
            >
                {{ hint }}
            </p>
        </CardContent>
    </Card>
</template>
