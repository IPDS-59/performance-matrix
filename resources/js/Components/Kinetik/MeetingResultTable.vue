<script setup lang="ts">
import { computed } from 'vue';
import { ExternalLink } from 'lucide-vue-next';
import type { RecapSegment, TeamRecapEvidence } from '@/types';
import { formatNumber } from '@/composables/useNumber';
import { evidenceTypeLabel } from '@/composables/useRkRecap';

/**
 * The results of one weekly or monthly meeting as a sheet, for a plain member: one row per RK with the PJ's paraphrase
 * and numbers, then the bukti rapat. The kegiatan and names of the members are not part of it.
 */
const props = defineProps<{ title: string; segments: RecapSegment[]; evidences?: TeamRecapEvidence[] }>();

const rows = computed(() => props.segments.flatMap(s => s.rows.map(r => ({ ...r, project_name: s.project_id ? s.project_name : null }))));
const withUnit = (n: number | null | undefined, unit?: string | null) => (n === null || n === undefined ? '–' : `${formatNumber(n)}${unit ? ` ${unit}` : ''}`);
</script>

<template>
    <section aria-labelledby="hasil-rapat" class="mb-6">
        <h3 id="hasil-rapat" class="mb-1 text-base font-semibold text-gray-900">{{ title }}</h3>
        <p class="mb-3 text-xs text-gray-600">Ditulis oleh PJ tim. Anda hanya dapat membaca.</p>

        <div class="overflow-x-auto rounded-lg border bg-white">
            <table class="w-full border-collapse text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="w-10 border px-3 py-2">No</th>
                        <th class="border px-3 py-2">Projek</th>
                        <th class="border px-3 py-2">Rencana Kinerja (RK)</th>
                        <th class="min-w-[18rem] border px-3 py-2">Hasil rapat</th>
                        <th class="border px-3 py-2 text-right">Target</th>
                        <th class="border px-3 py-2 text-right">Capaian</th>
                        <th class="border px-3 py-2 text-right">%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="row.row_key" class="align-top">
                        <td class="border px-3 py-2 tabular-nums text-gray-600">{{ i + 1 }}</td>
                        <td class="border px-3 py-2">{{ row.project_name ?? '–' }}</td>
                        <td class="border px-3 py-2">{{ row.rk_description }}</td>
                        <td class="border px-3 py-2 whitespace-pre-line">
                            <template v-if="row.pj_uraian">{{ row.pj_uraian }}</template>
                            <span v-else class="italic text-gray-500">Belum ditulis PJ</span>
                        </td>
                        <td class="whitespace-nowrap border px-3 py-2 text-right tabular-nums">{{ row.target ? withUnit(row.target, row.target_unit) : '–' }}</td>
                        <td class="whitespace-nowrap border px-3 py-2 text-right tabular-nums">{{ row.target ? withUnit(row.realization, row.target_unit) : '–' }}</td>
                        <td class="whitespace-nowrap border px-3 py-2 text-right tabular-nums">{{ row.achievement !== null ? `${formatNumber(row.achievement)}%` : '–' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="7" class="border px-3 py-8 text-center text-gray-500">Belum ada hasil rapat untuk periode ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template v-if="evidences">
            <h4 class="mb-2 mt-4 text-sm font-semibold text-gray-700">Bukti rapat</h4>
            <p v-if="!evidences.length" class="rounded-md border border-dashed bg-gray-50 py-6 text-center text-sm text-gray-600">Belum ada bukti rapat.</p>
            <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-md border bg-white">
                <li v-for="ev in evidences" :key="ev.id" class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ evidenceTypeLabel[ev.type] ?? ev.type }}</span>
                        <span class="ml-2 text-sm text-gray-800">{{ ev.title || '—' }}</span>
                    </div>
                    <a :href="ev.url" target="_blank" rel="noopener noreferrer" class="inline-flex shrink-0 items-center gap-1 text-xs text-primary hover:underline">Buka <ExternalLink class="h-3 w-3" /></a>
                </li>
            </ul>
        </template>
    </section>
</template>
