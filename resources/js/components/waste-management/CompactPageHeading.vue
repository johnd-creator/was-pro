<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}>();
</script>
<template>
    <header class="min-w-0 space-y-1" data-page-heading="compact">
        <h1
            class="text-lg leading-6 font-semibold tracking-tight text-foreground"
        >
            {{ title }}
        </h1>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
