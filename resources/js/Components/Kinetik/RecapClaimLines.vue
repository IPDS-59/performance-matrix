<script setup lang="ts">
import type { RecapClaimLine } from '@/types';
import { achievementColor, achievementOf, percentLabel, type NumberForm } from '@/composables/useRecapNumbers';

/**
 * The member kegiatan of a recap row: name, kipApp uraian and their numbers.
 * The PJ edits target, realisasi and satuan in place (see WeeklyProjectCard).
 */
defineProps<{
    claims: RecapClaimLine[];
    /** Edited numbers by claim id, owned by the card. */
    numbers: Record<number, NumberForm>;
    canEdit: boolean;
}>();

const numberField = 'h-8 rounded-md border border-gray-200 bg-white px-1.5 text-center text-sm tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
const cols = 'grid grid-cols-[3.5rem_3.5rem_5.5rem_4.5rem] items-center gap-1.5';
</script>

<template>
    <ul class="space-y-1.5">
        <li v-for="line in claims" :key="line.claim_id" class="grid items-center gap-x-3 gap-y-1 md:grid-cols-[1fr_auto]">
            <div class="flex min-w-0 items-baseline gap-2 text-sm">
                <span class="shrink-0 rounded-full bg-indigo-50 px-2 py-px text-xs font-medium text-indigo-700">{{ line.name }}</span>
                <span class="min-w-0 text-gray-700">{{ line.uraian ?? 'Tanpa uraian' }}</span>
                <span v-if="line.adjusted_by" class="shrink-0 text-[11px] text-amber-700" :title="`Angka dikoreksi ${line.adjusted_by}`">dikoreksi</span>
            </div>
            <div>
                <!-- The column titles sit in the table header on wide screens only. -->
                <div :class="[cols, 'mb-0.5 text-center text-[11px] text-gray-500 md:hidden']" aria-hidden="true">
                    <span>Target</span><span>Realisasi</span><span>Satuan</span><span class="text-right">Capaian</span>
                </div>
                <div v-if="canEdit && numbers[line.claim_id]" :class="cols">
                    <input v-model="numbers[line.claim_id].target" type="number" min="0" step="any" :class="numberField" :aria-label="`Target ${line.name}`" />
                    <input v-model="numbers[line.claim_id].realization" type="number" min="0" step="any" :class="numberField" :aria-label="`Realisasi ${line.name}`" />
                    <input v-model="numbers[line.claim_id].target_unit" type="text" :class="numberField" :aria-label="`Satuan ${line.name}`" />
                    <span :class="['text-right text-sm font-semibold tabular-nums', achievementColor(achievementOf(numbers[line.claim_id].target, numbers[line.claim_id].realization))]">
                        {{ percentLabel(achievementOf(numbers[line.claim_id].target, numbers[line.claim_id].realization)) }}
                    </span>
                </div>
                <div v-else :class="[cols, 'text-center text-sm tabular-nums text-gray-700']">
                    <span>{{ line.target ?? '—' }}</span><span>{{ line.realization ?? '—' }}</span>
                    <span class="truncate text-gray-500">{{ line.target_unit ?? '' }}</span>
                    <span :class="['text-right font-semibold', achievementColor(line.achievement)]">{{ percentLabel(line.achievement) }}</span>
                </div>
            </div>
        </li>
    </ul>
</template>
