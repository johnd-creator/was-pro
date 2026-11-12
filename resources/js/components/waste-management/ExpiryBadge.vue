<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { getWasteStorageState } from '@/lib/waste-storage';

interface Props {
    expiryDate: string | null;
    referenceDate?: string;
    remainingQuantity?: number;
    simulated?: boolean;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    simulated: false,
});

const referenceDate = computed(() => {
    const parts = props.referenceDate?.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return parts
        ? new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]))
        : new Date();
});

const state = computed(() =>
    getWasteStorageState(
        props.expiryDate,
        props.remainingQuantity,
        props.simulated,
        referenceDate.value,
    ),
);

const badgeClasses = computed(() => {
    const base = 'inline-flex items-center rounded-full font-medium';
    const sizeClasses = {
        sm: 'px-2 py-0.5 text-xs',
        md: 'px-2.5 py-0.5 text-xs',
        lg: 'px-3 py-1 text-sm',
    };
    const statusClasses = {
        completed:
            'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        expired: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        expiring_soon:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        fresh: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        na: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return cn(base, sizeClasses[props.size], statusClasses[state.value.status]);
});

const icon = computed(() => {
    const icons = {
        completed: '✓',
        expired: '!',
        expiring_soon: '!',
        fresh: '✓',
        na: '-',
    };
    return icons[state.value.status];
});

const historicalDeadline = computed(() =>
    props.expiryDate
        ? `Batas simpan historis: ${props.expiryDate.slice(0, 10)}`
        : undefined,
);
</script>

<template>
    <span :class="badgeClasses" :title="historicalDeadline">
        <span class="mr-1">{{ icon }}</span>
        {{ state.label }}
    </span>
</template>
