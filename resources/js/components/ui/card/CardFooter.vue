<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card-footer"
    :class="cn('flex items-center px-[var(--card-padding,1.5rem)] [.border-t]:pt-[var(--card-padding,1.5rem)]', props.class)"
  >
    <slot />
  </div>
</template>
