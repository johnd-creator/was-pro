<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useReducedMotion } from '@/composables/useReducedMotion';

interface SkeletonCardProps {
    variant?: 'default' | 'compact' | 'stat' | 'header-only';
    showIcon?: boolean;
    lines?: number;
}

const props = withDefaults(defineProps<SkeletonCardProps>(), {
    variant: 'default',
    showIcon: true,
    lines: 3,
});

const { prefersReducedMotion } = useReducedMotion();

const animationClass = computed(() =>
    prefersReducedMotion.value ? '' : 'animate-pulse',
);

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'compact':
            return 'h-24';
        case 'stat':
            return 'h-32';
        case 'header-only':
            return 'h-auto';
        default:
            return 'h-48';
    }
});
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="space-y-3 pb-4">
            <div class="flex items-center justify-between">
                <div
                    v-if="showIcon"
                    :class="['h-10 w-10 rounded-lg bg-muted', animationClass]"
                    aria-hidden="true"
                />
                <div
                    :class="['h-4 w-24 rounded bg-muted', animationClass]"
                    aria-hidden="true"
                />
            </div>
        </CardHeader>
        <CardContent
            v-if="variant !== 'header-only'"
            :class="['space-y-3', variantClasses]"
        >
            <div
                :class="['h-8 w-16 rounded bg-muted', animationClass]"
                aria-hidden="true"
            />
            <div
                v-for="i in lines"
                :key="i"
                :class="[
                    'h-4 rounded bg-muted',
                    i === lines ? 'w-3/4' : 'w-full',
                    animationClass,
                ]"
                aria-hidden="true"
            />
        </CardContent>
    </Card>
</template>
