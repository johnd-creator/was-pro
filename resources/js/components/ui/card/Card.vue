<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground flex min-w-0 flex-col gap-[var(--card-gap,1.5rem)] rounded-xl border py-[var(--card-padding,1.5rem)] shadow-sm',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
