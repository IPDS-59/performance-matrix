<script setup lang="ts">
import { Check } from "lucide-vue-next"
import { inject, onMounted, onUpdated, ref } from "vue"
import { CommandItem } from "@/Components/ui/command"
import { collectingKey, selectKey } from "./context"

const props = defineProps<{ value: string | number | null, disabled?: boolean }>()

const ctx = inject(selectKey)!
const collecting = inject(collectingKey, false)
const labelEl = ref<HTMLElement>()

function reportLabel() {
  if (collecting)
    ctx.labels.set(props.value, labelEl.value?.textContent?.trim() ?? "")
}
onMounted(reportLabel)
onUpdated(reportLabel)
</script>

<template>
  <span v-if="collecting" ref="labelEl"><slot /></span>
  <CommandItem
    v-else
    data-slot="select-item"
    :value="value"
    :disabled="disabled"
    class="group"
    @select="ctx.select(value)"
  >
    <span class="min-w-0 flex-1 break-words"><slot /></span>
    <Check class="ml-auto size-4 opacity-0 group-data-[state=checked]:opacity-100" />
  </CommandItem>
</template>
