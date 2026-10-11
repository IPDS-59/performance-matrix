<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ChevronDown } from "lucide-vue-next"
import { computed, inject } from "vue"
import { PopoverTrigger } from "@/Components/ui/popover"
import { cn } from "@/lib/utils"
import { selectKey } from "./context"

const props = withDefaults(
  defineProps<{ class?: HTMLAttributes["class"], size?: "sm" | "default" }>(),
  { size: "default" },
)

const ctx = inject(selectKey)!
const isEmpty = computed(() => ctx.value.value === undefined || !ctx.labels.has(ctx.value.value))
</script>

<template>
  <PopoverTrigger
    as="button"
    type="button"
    role="combobox"
    data-slot="select-trigger"
    :data-size="size"
    :data-placeholder="isEmpty ? '' : undefined"
    :aria-expanded="ctx.open.value"
    :disabled="ctx.disabled.value"
    :class="cn(
      'border-input data-[placeholder]:text-muted-foreground [&_svg:not([class*=\'text-\'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-9 data-[size=sm]:h-8 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4',
      props.class,
    )"
  >
    <slot />
    <ChevronDown class="size-4 opacity-50" />
  </PopoverTrigger>
</template>
