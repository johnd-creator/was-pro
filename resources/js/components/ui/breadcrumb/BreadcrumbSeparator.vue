<script lang="ts" setup>
import type { HTMLAttributes } from "vue"
import { ChevronRight } from "lucide-vue-next"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <li
    data-slot="breadcrumb-separator"
    role="presentation"
    aria-hidden="true"
    :class="cn('[&>svg]:size-3.5 text-slate-400 dark:text-slate-600', props.class)"
  >
    <slot>
      <ChevronRight />
    </slot>
  </li>
</template>
