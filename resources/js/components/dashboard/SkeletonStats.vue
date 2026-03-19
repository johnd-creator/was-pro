<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useReducedMotion } from '@/composables/useReducedMotion';

interface SkeletonStatsProps {
    count?: number;
}

const { count = 4 } = defineProps<SkeletonStatsProps>();

const { prefersReducedMotion } = useReducedMotion();

const animationClass = computed(() =>
    prefersReducedMotion.value ? '' : 'animate-pulse',
);
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card
            v-for="i in count"
            :key="i"
            :class="animationClass"
            aria-hidden="true"
        >
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <div class="h-4 w-24 rounded bg-muted" />
                <div class="h-4 w-4 rounded bg-muted" />
            </CardHeader>
            <CardContent>
                <div class="mb-2 h-8 w-16 rounded bg-muted" />
                <div class="h-3 w-32 rounded bg-muted" />
            </CardContent>
        </Card>
    </div>
</template>
