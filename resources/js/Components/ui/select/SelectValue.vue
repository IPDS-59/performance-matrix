<script setup lang="ts">
import { computed, inject } from "vue"
import { selectKey } from "./context"

const props = defineProps<{ placeholder?: string }>()

const ctx = inject(selectKey)!
const label = computed(() => {
  const v = ctx.value.value
  return v === undefined ? undefined : ctx.labels.get(v)
})
</script>

<template>
  <span data-slot="select-value" class="pointer-events-none truncate">
    <slot>{{ label ?? props.placeholder }}</slot>
  </span>
</template>
