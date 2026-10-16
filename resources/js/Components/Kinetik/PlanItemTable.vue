<script setup lang="ts">
import type { PlanItemRow } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { planStage, toneClass } from '@/composables/usePlanStage';
import { achievementPercent, periodText, withUnit } from '@/composables/useAchievement';
import { useDateFormat } from '@/composables/useDateFormat';

/** Short rows of kegiatan; a click opens the detail. */
const props = defineProps<{ rows: PlanItemRow[]; canSendToAtasan: boolean; emptyText: string }>();
const emit = defineEmits<{ open: [item: PlanItemRow] }>();
const { formatDate } = useDateFormat();
const short = (d: string) => formatDate(d, { day: 'numeric', month: 'short' });
const stageOf = (row: PlanItemRow) => planStage(row, props.canSendToAtasan);
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>Kegiatan</TableHead><TableHead>RK</TableHead><TableHead>Tanggal</TableHead>
                <TableHead>Target</TableHead><TableHead>Capaian</TableHead><TableHead>Status</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="row in rows" :key="row.id" class="cursor-pointer" tabindex="0" @click="emit('open', row)" @keydown.enter="emit('open', row)">
                <TableCell class="max-w-xs truncate font-medium">{{ row.description }}</TableCell>
                <TableCell class="max-w-[14rem] truncate text-gray-600">{{ row.rk_name }}</TableCell>
                <TableCell class="whitespace-nowrap tabular-nums">{{ periodText(row, short) }}</TableCell>
                <TableCell class="whitespace-nowrap tabular-nums">{{ withUnit(row.target, row.target_unit) }}</TableCell>
                <TableCell class="whitespace-nowrap tabular-nums">
                    {{ withUnit(row.capaian, row.target_unit) }}
                    <span v-if="achievementPercent(row.capaian, row.target) !== null" class="text-gray-600">({{ achievementPercent(row.capaian, row.target) }}%)</span>
                </TableCell>
                <TableCell><span :class="['whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium', toneClass[stageOf(row).tone]]">{{ stageOf(row).label }}</span></TableCell>
            </TableRow>
        </TableBody>
    </Table>
    <p v-if="!rows.length" class="px-4 py-8 text-center text-sm text-gray-600">{{ emptyText }}</p>
</template>
