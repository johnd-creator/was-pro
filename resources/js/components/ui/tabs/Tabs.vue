<script setup lang="ts">
import { provide, ref, watch } from 'vue'

type Props = {
  defaultValue?: string
  modelValue?: string
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  defaultValue: '',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const activeTab = ref(props.modelValue || props.defaultValue)

watch(() => props.modelValue, (newValue) => {
  if (newValue !== undefined) {
    activeTab.value = newValue
  }
})

function setActiveTab(value: string) {
  activeTab.value = value
  emit('update:modelValue', value)
}

provide('tabs', {
  activeTab,
  setActiveTab,
})
</script>

<template>
  <div :class="props.class">
    <slot />
  </div>
</template>
