<script setup lang="ts">
import { useVModel } from "@vueuse/core"
import { provide, reactive, ref, toRef } from "vue"
import { Popover } from "@/Components/ui/popover"
import { selectKey } from "./context"
import type { SelectValueType } from "./context"

const props = defineProps<{
  modelValue?: SelectValueType
  defaultValue?: SelectValueType
  disabled?: boolean
}>()
const emit = defineEmits<{ "update:modelValue": [value: string | number | null] }>()

const value = useVModel(props, "modelValue", emit, { passive: true, defaultValue: props.defaultValue })
const open = ref(false)

provide(selectKey, {
  value,
  open,
  labels: reactive(new Map<string | number | null, string>()),
  disabled: toRef(props, "disabled"),
  select(next) {
    value.value = next
    open.value = false
  },
})
</script>

<template>
  <Popover v-model:open="open">
    <slot />
  </Popover>
</template>
