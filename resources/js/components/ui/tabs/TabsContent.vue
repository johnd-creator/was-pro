<script setup lang="ts">
import { inject, computed } from 'vue'

type Props = {
  value: string
  class?: string
}

const props = defineProps<Props>()

const tabs = inject<{ activeTab: { value: string } }>('tabs')

const isVisible = computed(() => tabs?.activeTab.value === props.value)
</script>

<template>
  <div v-if="isVisible" :class="['mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2', props.class]">
    <slot />
  </div>
</template>
