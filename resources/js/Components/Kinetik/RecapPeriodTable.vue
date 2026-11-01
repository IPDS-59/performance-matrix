<script setup lang="ts">
import { computed } from 'vue';
import type { PeriodRow } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

/** A list of periods (months or weeks) with their counts. A click opens that period. */
const p = withDefaults(defineProps<{ rows: PeriodRow[]; periodLabel: string; writtenLabel?: string; lockedLabel?: string; doneStatus?: string }>(), { writtenLabel: 'RK sudah ditulis', lockedLabel: 'Terkunci', doneStatus: 'Terkunci' });

// The PJ's weekly and monthly pages send `rks` and have no lock: the status is how many RK already have a paraphrase.
const byParaphrase = computed(() => p.rows.length > 0 && p.rows.every((r) => r.rks !== undefined));
const emit = defineEmits<{ open: [key: number | string] }>();

const label = (r: PeriodRow) => (status(r) === 'DONE' ? (byParaphrase.value ? 'Selesai ditulis' : p.doneStatus) : status(r));
const status = (r: PeriodRow) => {
    if (byParaphrase.value) {
        if (r.activities === 0) return 'Belum ada kegiatan';
        if (r.written === 0) return 'Belum ditulis';
        return r.written < (r.rks ?? 0) ? 'Sedang ditulis' : 'DONE';
    }
    return (r.locked ?? 0) > 0 && (r.locked ?? 0) >= r.written && r.written > 0 ? 'DONE' : r.written > 0 ? 'Sedang ditulis' : r.activities > 0 ? 'Belum ditulis' : 'Belum ada kegiatan';
};
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
            <TableHeader><TableRow><TableHead>{{ periodLabel }}</TableHead><TableHead>Kegiatan</TableHead><TableHead>{{ byParaphrase ? 'RK dengan kegiatan' : writtenLabel }}</TableHead><TableHead>{{ byParaphrase ? 'RK sudah ditulis' : lockedLabel }}</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
            <TableBody>
                <TableRow v-for="r in rows" :key="r.key" class="cursor-pointer" tabindex="0" :aria-label="`Buka rekap ${r.label}`" @click="emit('open', r.key)" @keydown.enter="emit('open', r.key)">
                    <TableCell class="font-medium">{{ r.label }}<span v-if="r.current" class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">Sekarang</span></TableCell>
                    <TableCell class="tabular-nums">{{ r.activities }}</TableCell>
                    <TableCell class="tabular-nums">{{ byParaphrase ? r.rks : r.written }}</TableCell>
                    <TableCell class="tabular-nums">{{ byParaphrase ? r.written : r.locked }}</TableCell>
                    <TableCell><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', tone[status(r)]]">{{ label(r) }}</span></TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
