<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

interface Props {
    title: string;
    value: string | number;
    unit?: string;
    href?: string;
    color?: 'blue' | 'emerald' | 'orange' | 'red';
}

const props = withDefaults(defineProps<Props>(), {
    unit: '',
    color: 'blue',
});

const colorClasses = computed(() => {
    const colors = {
        blue: 'from-blue-500 to-blue-600',
        emerald: 'from-emerald-500 to-emerald-600',
        orange: 'from-orange-500 to-orange-600',
        red: 'from-red-500 to-red-600',
    };

    return colors[props.color];
});
</script>

<template>
    <Card
        :class="[
            'border-0 bg-gradient-to-br text-white shadow-lg transition-all duration-300 hover:shadow-xl hover:scale-[1.02]',
            colorClasses,
        ]"
    >
        <CardContent class="p-4">
            <p class="text-xs font-medium text-white/80 uppercase">
                {{ title }}
            </p>
            <p class="mt-1 text-2xl font-bold tabular-nums">
                {{ value }}
                <span v-if="unit" class="text-sm font-normal text-white/70">
                    {{ unit }}
                </span>
            </p>
        </CardContent>
    </Card>
</template>
