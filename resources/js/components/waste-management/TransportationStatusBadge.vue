<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    status: 'pending' | 'in_transit' | 'delivered' | 'cancelled';
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const badgeClasses = computed(() => {
    const base = 'inline-flex items-center rounded-full font-medium';
    const sizeClasses = {
        sm: 'px-2 py-0.5 text-xs',
        md: 'px-2.5 py-0.5 text-xs',
        lg: 'px-3 py-1 text-sm',
    };

    const statusClasses = {
        pending:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        in_transit:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        delivered:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };

    return cn(base, sizeClasses[props.size], statusClasses[props.status]);
});

const label = computed(() => {
    const labels = {
        pending: 'Menunggu',
        in_transit: 'Dalam Perjalanan',
        delivered: 'Terkirim',
        cancelled: 'Dibatalkan',
    };
    return labels[props.status];
});
</script>

<template>
    <span :class="badgeClasses">{{ label }}</span>
</template>
