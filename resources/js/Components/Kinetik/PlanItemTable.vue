<script setup lang="ts">
import { computed } from 'vue';
import type { PlanItemRow } from '@/types';
import { Checkbox } from '@/Components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { planStage, toneClass } from '@/composables/usePlanStage';
import { achievementPercent, periodText, withUnit } from '@/composables/useAchievement';
import { useDateFormat } from '@/composables/useDateFormat';

/** Short rows of kegiatan; a click opens the detail. With the send setting on, rows in kipApp can be checked. */
const props = defineProps<{ rows: PlanItemRow[]; canSendToAtasan: boolean; emptyText: string; selected: number[] }>();
const emit = defineEmits<{ open: [item: PlanItemRow]; toggle: [id: number, on: boolean]; 'toggle-all': [rows: PlanItemRow[], on: boolean] }>();
const selectable = computed(() => props.rows.filter(r => r.can_send));
const allChecked = computed(() => selectable.value.length > 0 && selectable.value.every(r => props.selected.includes(r.id)));
const { formatDate } = useDateFormat();
const short = (d: string) => formatDate(d, { day: 'numeric', month: 'short' });
const stageOf = (row: PlanItemRow) => planStage(row, props.canSendToAtasan);
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead v-if="canSendToAtasan" class="w-8">
                    <Checkbox :model-value="allChecked" :disabled="!selectable.length" aria-label="Pilih semua kegiatan yang bisa dikirim" @update:model-value="(v: boolean | 'indeterminate') => emit('toggle-all', rows, v === true)" />
                </TableHead>
                <TableHead>Kegiatan</TableHead><TableHead>RK</TableHead><TableHead>Tanggal</TableHead>
                <TableHead>Target</TableHead><TableHead>Capaian</TableHead><TableHead>Bukti</TableHead><TableHead>Dikirim</TableHead><TableHead>Status</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="row in rows" :key="row.id" class="cursor-pointer" tabindex="0" @click="emit('open', row)" @keydown.enter="emit('open', row)">
                <TableCell v-if="canSendToAtasan" class="w-8" @click.stop>
                    <Checkbox :model-value="selected.includes(row.id)" :disabled="!row.can_send" :aria-label="`Pilih ${row.description}`" @update:model-value="(v: boolean | 'indeterminate') => emit('toggle', row.id, v === true)" />
                </TableCell>
                <TableCell class="max-w-xs truncate font-medium">{{ row.description }}</TableCell>
                <TableCell class="max-w-[14rem] truncate text-gray-600">{{ row.rk_name }}</TableCell>
                <TableCell class="whitespace-nowrap tabular-nums">{{ periodText(row, short) }}</TableCell>
                <TableCell class="whitespace-nowrap tabular-nums">{{ withUnit(row.target, row.target_unit) }}</TableCell>
                <TableCell class="whitespace-nowrap tabular-nums">
                    {{ withUnit(row.capaian, row.target_unit) }}
                    <span v-if="achievementPercent(row.capaian, row.target) !== null" class="text-gray-600">({{ achievementPercent(row.capaian, row.target) }}%)</span>
                </TableCell>
                <TableCell class="whitespace-nowrap" @click.stop>
                    <a v-if="row.evidence_url" :href="row.evidence_url" target="_blank" rel="noopener noreferrer" class="text-indigo-700 underline">Lihat</a>
                    <span v-else class="text-xs italic text-gray-600">Tidak ada bukti dukung</span>
                </TableCell>
                <TableCell class="whitespace-nowrap tabular-nums">{{ row.sent_at ? formatDate(row.sent_at, { day: 'numeric', month: 'short', year: 'numeric' }) : '–' }}</TableCell>
                <TableCell><span :class="['whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium', toneClass[stageOf(row).tone]]">{{ stageOf(row).label }}</span></TableCell>
            </TableRow>
        </TableBody>
    </Table>
    <p v-if="!rows.length" class="px-4 py-8 text-center text-sm text-gray-600">{{ emptyText }}</p>
</template>
