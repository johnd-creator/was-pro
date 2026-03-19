<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    status: 'draft' | 'pending_review' | 'approved' | 'rejected';
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
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        pending_review:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        approved:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };

    return cn(base, sizeClasses[props.size], statusClasses[props.status]);
});

const label = computed(() => {
    const labels = {
        draft: 'Draf',
        pending_review: 'Menunggu Tinjauan',
        approved: 'Disetujui',
        rejected: 'Ditolak',
    };
    return labels[props.status];
});
</script>

<template>
    <span :class="badgeClasses">{{ label }}</span>
</template>
