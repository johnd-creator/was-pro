<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    title: string;
    value: number | string;
    subtext?: string;
    icon?: Component;
    color?: 'blue' | 'green' | 'red' | 'orange' | 'purple' | 'gray';
    alert?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    color: 'blue',
    alert: false,
});

const colorClasses = computed(() => {
    const colors = {
        blue: 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
        green: 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400',
        red: 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400',
        orange: 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400',
        purple: 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
        gray: 'bg-gray-50 dark:bg-gray-900/20 text-gray-600 dark:text-gray-400',
    };
    return colors[props.color];
});
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
        >
            <CardTitle class="text-sm font-medium">{{ title }}</CardTitle>
            <div v-if="icon" :class="colorClasses" class="rounded-md p-2">
                <component :is="icon" class="h-4 w-4" />
            </div>
            <AlertCircle v-else-if="alert" class="h-4 w-4 text-red-600" />
        </CardHeader>
        <CardContent>
            <div class="text-2xl font-bold">{{ value }}</div>
            <p v-if="subtext" class="text-xs text-muted-foreground">
                {{ subtext }}
            </p>
        </CardContent>
    </Card>
</template>
