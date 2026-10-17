<script setup lang="ts">
import { computed, ref } from 'vue';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';

/** A searchable select (Popover + Command). With `emptyLabel` the first choice clears the value. */
const props = defineProps<{
    options: Array<{ value: number; label: string }>;
    placeholder: string;
    searchPlaceholder?: string;
    emptyLabel?: string;
    disabled?: boolean;
}>();
const model = defineModel<number | null>({ required: true });

const open = ref(false);
const chosen = computed(() => props.options.find(o => o.value === model.value)?.label ?? (model.value === null ? props.emptyLabel : undefined));

function pick(value: number | null) {
    model.value = value;
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button type="button" variant="outline" role="combobox" :disabled="disabled" :aria-expanded="open" class="h-auto min-h-9 w-full justify-between whitespace-normal text-left font-normal">
                <span :class="{ 'text-gray-600': !chosen }">{{ chosen ?? placeholder }}</span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" aria-hidden="true" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
            <Command>
                <CommandInput :placeholder="searchPlaceholder ?? 'Cari…'" />
                <CommandList>
                    <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                    <CommandGroup>
                        <CommandItem v-if="emptyLabel" value="__none__" @select="pick(null)">
                            {{ emptyLabel }}
                            <Check v-if="model === null" class="ml-auto h-4 w-4" />
                        </CommandItem>
                        <CommandItem v-for="o in options" :key="o.value" :value="o.label" @select="pick(o.value)">
                            {{ o.label }}
                            <Check v-if="model === o.value" class="ml-auto h-4 w-4 shrink-0" />
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
