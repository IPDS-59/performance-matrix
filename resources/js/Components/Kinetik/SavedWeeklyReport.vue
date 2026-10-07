<script setup lang="ts">
import { computed } from 'vue';
import type { WeeklyRow, WeeklySegment } from '@/types';
import { Check } from 'lucide-vue-next';
import { rowAchievement } from '@/composables/useTeamWeeklyRecap';
import { useDateFormat } from '@/composables/useDateFormat';

/**
 * "Laporan Tersimpan" of the week, as in the mockup: per Projek the rows the PJ
 * saved, numbered, each with its result and Permasalahan / Solusi / RTL.
 */
const props = defineProps<{ segments: WeeklySegment[]; weekStart: string; weekEnd: string }>();
const { formatDate } = useDateFormat();

const saved = computed(() => props.segments
    .map(seg => ({ ...seg, rows: seg.rows.filter(r => r.saved) }))
    .filter(seg => seg.rows.length));

const title = (row: WeeklyRow) => row.pj_uraian || row.claims.map(c => c.uraian).filter(Boolean).join('; ') || row.claims[0]?.rk_description;
const sum = (row: WeeklyRow, key: 'target' | 'realization') => row.claims.reduce((n, c) => n + (c[key] ?? 0), 0);
const unit = (row: WeeklyRow) => row.claims.find(c => c.target_unit)?.target_unit ?? '';
const tone = (row: WeeklyRow) => {
    const a = rowAchievement(row);
    return a == null ? 'text-gray-500' : a >= 100 ? 'text-green-700' : a > 0 ? 'text-orange-600' : 'text-red-600';
};
const said = (v: string | null) => (v && v.trim() !== '-' ? v : '—');
const range = computed(() => `${formatDate(props.weekStart)} – ${formatDate(props.weekEnd)}`);
</script>

<template>
    <section v-if="saved.length" class="mb-6" aria-label="Laporan Tersimpan">
        <div class="mb-3 flex items-center gap-3">
            <h2 class="text-sm font-semibold text-gray-700">Laporan Tersimpan</h2>
            <span class="h-px flex-1 bg-gray-200" aria-hidden="true" />
            <span class="text-[11px] text-gray-400">Minggu {{ range }}</span>
        </div>

        <div class="space-y-3">
            <article v-for="seg in saved" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border border-green-200 bg-white">
                <header class="flex items-center justify-between gap-3 border-b border-green-100 bg-green-50/60 px-4 py-2.5">
                    <p class="text-sm"><span class="font-semibold text-gray-800">{{ seg.project_name }}</span> <span class="ml-1 text-xs text-gray-400">{{ range }}</span></p>
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700"><Check class="h-3 w-3" aria-hidden="true" /> Tersimpan</span>
                </header>
                <ol class="divide-y divide-gray-100">
                    <li v-for="(row, i) in seg.rows" :key="row.key" class="px-4 py-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm text-gray-900"><span class="mr-2 text-gray-400">{{ i + 1 }}.</span>{{ title(row) }}</p>
                            <p :class="['shrink-0 text-sm font-semibold tabular-nums', tone(row)]">{{ sum(row, 'realization') }}/{{ sum(row, 'target') }} {{ unit(row) }}</p>
                        </div>
                        <div class="mt-1.5 grid gap-3 border-l-2 border-amber-200 pl-3 text-xs sm:grid-cols-3">
                            <div><p class="text-gray-400">Permasalahan</p><p class="whitespace-pre-line text-gray-800">{{ said(row.obstacle) }}</p></div>
                            <div><p class="text-gray-400">Solusi</p><p class="whitespace-pre-line text-gray-800">{{ said(row.solution) }}</p></div>
                            <div><p class="text-gray-400">RTL</p><p class="whitespace-pre-line text-gray-800">{{ said(row.follow_up_plan) }}</p></div>
                        </div>
                    </li>
                </ol>
            </article>
        </div>
    </section>
</template>
