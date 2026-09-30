<script setup lang="ts">
import type { RecapSection } from '@/types';
import { ChevronRight } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';

/** The weeks of a month (or months of a quarter) as read-only, folding sections. */
defineProps<{ title: string; sections: RecapSection[] }>();

const { formatWeekRange } = useDateFormat();
const rowCount = (section: RecapSection) => section.segments.reduce((n, s) => n + s.rows.length, 0);
// Members write "-" when there is no obstacle.
const said = (v: string | null | undefined) => (v && v.trim() !== '-' ? v : null);
const pct = (v: number | null) => (v == null ? '—' : `${v.toFixed(0)}%`);
</script>

<template>
    <section class="mb-6 overflow-hidden rounded-md border bg-white" :aria-label="title">
        <div class="border-b bg-gray-50 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-800">{{ title }}</h2>
            <p class="text-xs text-gray-500">Hanya dibaca. Ubah isinya di halaman periode itu.</p>
        </div>
        <details v-for="section in sections" :key="section.start" class="group border-b last:border-b-0">
            <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-2.5 text-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                <ChevronRight class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-90" aria-hidden="true" />
                <span class="font-medium text-gray-800">{{ section.label }}</span>
                <span class="text-gray-500">{{ formatWeekRange(section.start, section.end) }}</span>
                <span :class="['ml-auto text-xs', rowCount(section) ? 'text-green-700' : 'text-gray-400']">
                    {{ rowCount(section) ? `${rowCount(section)} baris` : 'Belum ada rekap' }}
                </span>
            </summary>
            <div class="space-y-3 bg-gray-50/60 px-4 pb-4 pt-1">
                <div v-for="seg in section.segments" :key="seg.project_id ?? 'none'">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ seg.project_name }}</p>
                    <ul class="mt-1 divide-y rounded border bg-white">
                        <li v-for="row in seg.rows" :key="row.row_key" class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-[1fr_auto]">
                            <div class="min-w-0">
                                <p class="leading-snug text-gray-800">{{ row.pj_uraian || row.rk_description }}</p>
                                <p v-if="row.pj_uraian" class="text-xs text-gray-500">{{ row.rk_description }}</p>
                                <p v-if="said(row.obstacle) || said(row.solution) || said(row.follow_up_plan)" class="mt-0.5 text-xs text-gray-600">
                                    <template v-if="said(row.obstacle)">Permasalahan: {{ row.obstacle }}. </template>
                                    <template v-if="said(row.solution)">Solusi: {{ row.solution }}. </template>
                                    <template v-if="said(row.follow_up_plan)">RTL: {{ row.follow_up_plan }}</template>
                                </p>
                            </div>
                            <p class="whitespace-nowrap text-right text-xs tabular-nums text-gray-600">
                                {{ row.realization }} / {{ row.target }} {{ row.target_unit ?? '' }} · <span class="font-semibold">{{ pct(row.achievement) }}</span>
                            </p>
                        </li>
                    </ul>
                </div>
                <p v-if="!section.segments.length" class="text-sm text-gray-500">Belum ada klaim tersimpan.</p>
            </div>
        </details>
    </section>
</template>
