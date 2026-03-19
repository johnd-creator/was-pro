<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from 'reka-ui'

const props = defineProps<{
  class?: HTMLAttributes['class']
  defaultChecked?: boolean
  checked?: boolean
  disabled?: boolean
  readonly?: boolean
  name?: string
  id?: string
  required?: boolean
  value?: string
}>()

const emits = defineEmits<{
  (e: 'update:checked', payload: boolean): void
}>()

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
  <SwitchRoot
    v-bind="forwarded"
    :class="cn(
      'peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
      props.class
    )"
  >
    <SwitchThumb
      :class="cn(
        'pointer-events-none block h-4 w-4 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0'
      )"
    />
  </SwitchRoot>
</template>
