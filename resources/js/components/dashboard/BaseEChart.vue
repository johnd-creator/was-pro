<script setup lang="ts">
import VChart from 'vue-echarts';
import type { DashboardEChartsOption } from '@/composables/useDashboardEChart';

interface Props {
    option: DashboardEChartsOption;
    heightClass?: string;
}

withDefaults(defineProps<Props>(), {
    heightClass: 'h-[340px]',
});
</script>

<template>
    <div :class="['w-full', heightClass]">
        <VChart
            :option="option"
            autoresize
            class="h-full w-full"
        />
    </div>
</template>

