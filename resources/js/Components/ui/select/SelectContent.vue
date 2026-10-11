<script setup lang="ts">
import { inject } from "vue"
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandList } from "@/Components/ui/command"
import { PopoverContent } from "@/Components/ui/popover"
import SelectCollector from "./SelectCollector.vue"
import { selectKey } from "./context"

defineOptions({ inheritAttrs: false })

const ctx = inject(selectKey)!
</script>

<template>
  <SelectCollector>
    <slot />
  </SelectCollector>
  <PopoverContent
    v-bind="$attrs"
    data-slot="select-content"
    align="start"
    class="w-[var(--reka-popover-trigger-width)] min-w-[12rem] p-0"
  >
    <Command :model-value="ctx.value.value === undefined ? '' : ctx.value.value">
      <CommandInput placeholder="Cari…" />
      <CommandList>
        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
        <CommandGroup>
          <slot />
        </CommandGroup>
      </CommandList>
    </Command>
  </PopoverContent>
</template>
