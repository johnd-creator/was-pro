<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    expiryDate: string | null;
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

    const status = getExpiryStatus();
    const statusClasses = {
        expired: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        expiring_soon:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        fresh: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        na: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };

    return cn(base, sizeClasses[props.size], statusClasses[status]);
});

const label = computed(() => {
    if (!props.expiryDate) {
        return 'N/A';
    }

    const status = getExpiryStatus();
    const today = new Date();
    const expiryDate = new Date(props.expiryDate);
    const daysUntilExpiry = Math.ceil(
        (expiryDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    switch (status) {
        case 'expired':
            return `Expired (${Math.abs(daysUntilExpiry)} days ago)`;
        case 'expiring_soon':
            return `Expiring Soon (${daysUntilExpiry} days)`;
        case 'fresh':
            return `Fresh (${daysUntilExpiry} days)`;
        default:
            return 'N/A';
    }
});

const icon = computed(() => {
    const status = getExpiryStatus();
    const icons = {
        expired: '⚠️',
        expiring_soon: '⏰',
        fresh: '✓',
        na: '−',
    };
    return icons[status];
});

function getExpiryStatus(): 'expired' | 'expiring_soon' | 'fresh' | 'na' {
    if (!props.expiryDate) {
        return 'na';
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const expiryDate = new Date(props.expiryDate);
    expiryDate.setHours(0, 0, 0, 0);
    const daysUntilExpiry = Math.ceil(
        (expiryDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    if (daysUntilExpiry < 0) {
        return 'expired';
    }

    if (daysUntilExpiry <= 7) {
        return 'expiring_soon';
    }

    return 'fresh';
}
</script>

<template>
    <span :class="badgeClasses">
        <span class="mr-1">{{ icon }}</span>
        {{ label }}
    </span>
</template>
