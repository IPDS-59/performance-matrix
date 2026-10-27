<script setup lang="ts">
import type { RkPanel } from '@/types';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { panelState } from '@/composables/useRkRecap';

/** Searchable RK list of the team, with where each one stands in the meeting. */
defineProps<{ panels: RkPanel[] }>();
const selected = defineModel<number | null>({ required: true });

const mark = { locked: 'Terkunci', written: 'Sudah ditulis', empty: '' } as const;
</script>

<template>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <Label class="text-sm font-medium">Rencana Kinerja (RK)</Label>
        <Select :model-value="selected ?? undefined" @update:model-value="(v) => (selected = Number(v))">
            <SelectTrigger class="h-auto min-h-9 w-full max-w-xl whitespace-normal text-left" aria-label="Pilih RK"><SelectValue placeholder="Pilih RK" /></SelectTrigger>
            <SelectContent>
                <SelectItem v-for="p in panels" :key="p.performance_plan_id" :value="p.performance_plan_id">
                    {{ p.rk_description }}
                    <span class="text-gray-600">· {{ $n(p.entry_count) }} kegiatan<template v-if="mark[panelState(p)]"> · {{ mark[panelState(p)] }}</template></span>
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
