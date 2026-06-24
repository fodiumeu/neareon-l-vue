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
        'bg-card text-card-foreground flex flex-col gap-6 rounded-xl border border-border/80 py-6 shadow-sm shadow-black/10 backdrop-blur-md transition-[border-color,box-shadow] dark:border-border/70 dark:shadow-black/30',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
