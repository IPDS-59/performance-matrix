<script setup lang="ts">
import { computed, ref } from 'vue';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import type { Employee } from '@/types';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';

/** A searchable employee select with an "empty" choice. */
const props = defineProps<{ employees: Pick<Employee, 'id' | 'name' | 'display_name'>[]; emptyLabel: string }>();
const model = defineModel<number | null>({ required: true });

const open = ref(false);
const label = computed(() => {
    const emp = props.employees.find(e => e.id === model.value);
    return emp ? (emp.display_name || emp.name) : props.emptyLabel;
});

function pick(id: number | null) {
    model.value = id;
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button type="button" variant="outline" role="combobox" class="w-full justify-between font-normal">
                {{ label }}
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
            <Command>
                <CommandInput placeholder="Cari pegawai..." />
                <CommandList>
                    <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                    <CommandGroup>
                        <CommandItem value="__none__" @select="pick(null)">
                            {{ emptyLabel }}
                            <Check v-if="model === null" class="ml-auto h-4 w-4" />
                        </CommandItem>
                        <CommandItem v-for="emp in employees" :key="emp.id" :value="emp.display_name || emp.name" @select="pick(emp.id)">
                            {{ emp.display_name || emp.name }}
                            <Check v-if="model === emp.id" class="ml-auto h-4 w-4" />
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
