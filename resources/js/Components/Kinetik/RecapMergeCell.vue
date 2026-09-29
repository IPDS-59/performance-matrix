<script setup lang="ts">
import type { RecapRow } from '@/types';
import { Link2 } from 'lucide-vue-next';

/**
 * The Rencana Kinerja cell with the Gabungkan checkbox and the merge badge.
 * The RK text goes in the default slot.
 */
defineProps<{
    row: RecapRow;
    /** Rows in the row's merge group (1 = not merged). */
    size: number;
    lead: boolean;
    selectable: boolean;
    selected: boolean;
    busy?: boolean;
}>();

const emit = defineEmits<{ toggle: []; split: [] }>();
</script>

<template>
    <div class="flex items-start gap-2.5">
        <input
            v-if="selectable"
            type="checkbox"
            class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-primary focus-visible:ring-2 focus-visible:ring-primary"
            :checked="selected"
            :aria-label="`Pilih untuk digabungkan: ${row.rk_description}`"
            @change="emit('toggle')"
        />
        <div class="min-w-0">
            <slot />
            <p v-if="size > 1 && lead" class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                    <Link2 class="h-3 w-3" aria-hidden="true" /> Gabungan {{ size }} RK
                </span>
                <button
                    v-if="selectable"
                    type="button"
                    class="font-medium text-gray-500 hover:text-primary hover:underline disabled:opacity-50"
                    :disabled="busy"
                    @click="emit('split')"
                >
                    Pisahkan
                </button>
            </p>
            <p v-else-if="size > 1" class="mt-1 text-xs text-gray-500">Uraian dan parafrase mengikuti baris pertama grup</p>
        </div>
    </div>
</template>
