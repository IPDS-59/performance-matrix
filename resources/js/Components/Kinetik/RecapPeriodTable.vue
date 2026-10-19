<script setup lang="ts">
import type { PeriodRow } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

/** A list of periods (months or weeks) with their counts. A click opens that period. */
const p = withDefaults(defineProps<{ rows: PeriodRow[]; periodLabel: string; writtenLabel?: string; lockedLabel?: string; doneStatus?: string }>(), { writtenLabel: 'RK sudah ditulis', lockedLabel: 'Terkunci', doneStatus: 'Terkunci' });
const emit = defineEmits<{ open: [key: number | string] }>();

const label = (r: PeriodRow) => (status(r) === 'DONE' ? p.doneStatus : status(r));
const status = (r: PeriodRow) => (r.locked > 0 && r.locked >= r.written && r.written > 0 ? 'DONE' : r.written > 0 ? 'Sedang ditulis' : r.activities > 0 ? 'Belum ditulis' : 'Belum ada kegiatan');
const tone: Record<string, string> = {
    'DONE': 'bg-emerald-100 text-emerald-900',
    'Sedang ditulis': 'bg-blue-50 text-blue-800',
    'Belum ditulis': 'bg-amber-50 text-amber-800',
    'Belum ada kegiatan': 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <div class="rounded-lg border bg-white">
        <Table>
            <TableHeader><TableRow><TableHead>{{ periodLabel }}</TableHead><TableHead>Kegiatan</TableHead><TableHead>{{ writtenLabel }}</TableHead><TableHead>{{ lockedLabel }}</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
            <TableBody>
                <TableRow v-for="r in rows" :key="r.key" class="cursor-pointer" tabindex="0" :aria-label="`Buka rekap ${r.label}`" @click="emit('open', r.key)" @keydown.enter="emit('open', r.key)">
                    <TableCell class="font-medium">{{ r.label }}<span v-if="r.current" class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">Sekarang</span></TableCell>
                    <TableCell class="tabular-nums">{{ r.activities }}</TableCell>
                    <TableCell class="tabular-nums">{{ r.written }}</TableCell>
                    <TableCell class="tabular-nums">{{ r.locked }}</TableCell>
                    <TableCell><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', tone[status(r)]]">{{ label(r) }}</span></TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
