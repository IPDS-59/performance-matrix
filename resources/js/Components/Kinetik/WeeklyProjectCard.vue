<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { RecapClaimLine, RecapRow, RecapSegment } from '@/types';
import { Button } from '@/Components/ui/button';
import RecapMergeCell from '@/Components/Kinetik/RecapMergeCell.vue';
import { groupSize, isGroupLead, textKey, textTarget, type useRecapMerge } from '@/composables/useRecapMerge';
import { ChevronsUpDown } from 'lucide-vue-next';

/**
 * One Projek of the weekly team recap, laid out like the "rekap per projek"
 * mockup: each RK row lists the member kegiatan with their numbers, then the
 * PJ's parafrase and Permasalahan / Solusi / RTL. One Simpan per Projek.
 */
const props = defineProps<{
    seg: RecapSegment;
    rows: RecapRow[];
    /** PJ and the period is not locked. */
    canManage: boolean;
    canParaphrase: (row: RecapRow) => boolean;
    attention: number;
    sortDir: 'asc' | 'desc';
    merge: ReturnType<typeof useRecapMerge>;
    /** team_id and week_start for team-recap.weekly-project. */
    payload: { team_id: number | null; week_start: string };
}>();

const emit = defineEmits<{ sort: [] }>();

type TextForm = { uraian: string; obstacle: string; solution: string; follow_up_plan: string };
type NumberForm = { target: string; realization: string; target_unit: string };

const texts = ref<Record<string, TextForm>>({});
const numbers = ref<Record<number, NumberForm>>({});
const saving = ref(false);

// Members write "-" when there is nothing to report.
const said = (v: string | null | undefined) => (v && v.trim() !== '-' ? v : '');
const str = (v: number | null) => (v == null ? '' : String(v));

function textOf(row: RecapRow): TextForm {
    return { uraian: row.pj_uraian ?? '', obstacle: said(row.obstacle), solution: said(row.solution), follow_up_plan: said(row.follow_up_plan) };
}
function numberOf(line: RecapClaimLine): NumberForm {
    return { target: str(line.target), realization: str(line.realization), target_unit: line.target_unit ?? '' };
}

// Fresh forms whenever the server sends new rows (after a save, merge or split).
watch(() => props.seg, (seg) => {
    texts.value = Object.fromEntries(seg.rows.filter(r => !r.merge_key || isGroupLead(r)).map(r => [textKey(r), textOf(r)]));
    numbers.value = Object.fromEntries(seg.rows.flatMap(r => r.claims ?? []).map(c => [c.claim_id, numberOf(c)]));
}, { immediate: true });

const same = <T extends object>(a: T, b: T) => JSON.stringify(a) === JSON.stringify(b);

const changedRows = computed(() => props.seg.rows.filter(r => texts.value[textKey(r)] && textKey(r) === r.row_key && !same(texts.value[r.row_key], textOf(r))));
const changedClaims = computed(() => props.seg.rows.flatMap(r => r.claims ?? []).filter(c => !same(numbers.value[c.claim_id], numberOf(c))));
const changes = computed(() => changedRows.value.length + changedClaims.value.length);
const editable = computed(() => props.canManage || props.rows.some(props.canParaphrase));

function achievement(f: NumberForm): number | null {
    const t = Number(f.target);
    return f.target !== '' && t > 0 && f.realization !== '' ? (Number(f.realization) / t) * 100 : null;
}
function color(v: number | null): string {
    if (v == null) return 'text-gray-400';
    if (v >= 100) return 'text-green-600';
    if (v >= 50) return 'text-orange-600';
    return 'text-red-600';
}
const pct = (v: number | null) => (v == null ? '—' : `${Math.round(v)}%`);

function save() {
    saving.value = true;
    router.post(route('team-recap.weekly-project'), {
        ...props.payload,
        rows: changedRows.value.map(r => ({ ...textTarget(r), ...texts.value[r.row_key] })),
        claims: changedClaims.value.map(c => ({
            id: c.claim_id,
            target: numbers.value[c.claim_id].target || null,
            realization: numbers.value[c.claim_id].realization || null,
            target_unit: numbers.value[c.claim_id].target_unit || null,
        })),
    }, { preserveScroll: true, onFinish: () => (saving.value = false) });
}

const claimCount = computed(() => props.seg.rows.reduce((n, r) => n + (r.claims?.length ?? 0), 0));
const field = 'w-full rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
const numberField = 'h-8 rounded-md border border-gray-200 bg-white px-1.5 text-center text-sm tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
</script>

<template>
    <section class="overflow-hidden rounded-md border bg-white" :aria-label="`Projek ${seg.project_name}`">
        <header class="flex flex-col gap-2 border-b bg-gray-50 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Projek</p>
                <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                <p v-if="seg.leader_rk" class="mt-0.5 text-xs leading-snug text-primary">RK Ketua: {{ seg.leader_rk }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:shrink-0 sm:flex-col sm:items-end sm:gap-1">
                <span class="text-xs text-gray-500">{{ claimCount }} klaim<template v-if="canManage"> · PJ pilih mana yang digabung</template></span>
                <span v-if="attention > 0" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">{{ attention }} perlu perhatian</span>
            </div>
        </header>

        <div v-if="canManage" class="flex flex-wrap items-center justify-between gap-2 border-b bg-primary/5 px-4 py-2">
            <p class="text-xs font-medium text-primary">Centang baris yang ingin digabung, lalu klik tombol gabung</p>
            <Button size="sm" variant="outline" class="h-7 px-2.5 text-xs" :disabled="merge.selectedCount(seg) < 2 || merge.busy.value" @click="merge.merge(seg, rows)">
                Gabungkan yang dipilih<template v-if="merge.selectedCount(seg) >= 2"> ({{ merge.selectedCount(seg) }})</template>
            </Button>
        </div>

        <div class="hidden grid-cols-[1fr_auto] gap-3 border-b px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-gray-400 md:grid" :class="canManage ? 'pl-11' : ''">
            <span>Rencana Kinerja · Anggota · Uraian dari kipApp</span>
            <span class="grid grid-cols-[3.5rem_3.5rem_5.5rem_4.5rem] gap-1.5 text-center">
                <span>Target</span><span>Realisasi</span><span>Satuan</span>
                <button type="button" class="inline-flex items-center justify-end gap-0.5 uppercase hover:text-gray-600" title="Urutkan menurut capaian" @click="emit('sort')">
                    Capaian <ChevronsUpDown class="h-3 w-3" :class="{ 'rotate-180': sortDir === 'desc' }" />
                </button>
            </span>
        </div>

        <div class="divide-y divide-gray-100">
            <div v-for="row in rows" :key="row.row_key" :class="['px-4 py-3', groupSize(seg, row) > 1 ? 'border-l-2 border-l-primary/60' : '']">
                <RecapMergeCell
                    :row="row"
                    :size="groupSize(seg, row)"
                    :lead="isGroupLead(row)"
                    :selectable="canManage"
                    :selected="merge.isSelected(seg, row)"
                    :busy="merge.busy.value"
                    @toggle="merge.toggle(seg, row)"
                    @split="merge.split(row)"
                >
                    <p class="text-sm font-medium leading-snug text-gray-800">{{ row.rk_description }}</p>
                </RecapMergeCell>

                <div :class="canManage ? 'md:pl-[1.625rem]' : ''">
                    <!-- Member kegiatan -->
                    <ul class="mt-2 space-y-1.5">
                        <li v-for="line in row.claims ?? []" :key="line.claim_id" class="grid items-center gap-x-3 gap-y-1 md:grid-cols-[1fr_auto]">
                            <div class="flex min-w-0 items-baseline gap-2 text-sm">
                                <span class="shrink-0 rounded-full bg-indigo-50 px-2 py-px text-xs font-medium text-indigo-700">{{ line.name }}</span>
                                <span class="min-w-0 text-gray-700">{{ line.uraian ?? 'Tanpa uraian' }}</span>
                                <span v-if="line.adjusted_by" class="shrink-0 text-[11px] text-amber-700" :title="`Angka dikoreksi ${line.adjusted_by}`">dikoreksi</span>
                            </div>
                            <div v-if="canManage && numbers[line.claim_id]" class="grid grid-cols-[3.5rem_3.5rem_5.5rem_4.5rem] items-center gap-1.5">
                                <input v-model="numbers[line.claim_id].target" type="number" min="0" step="any" :class="numberField" :aria-label="`Target ${line.name}`" />
                                <input v-model="numbers[line.claim_id].realization" type="number" min="0" step="any" :class="numberField" :aria-label="`Realisasi ${line.name}`" />
                                <input v-model="numbers[line.claim_id].target_unit" type="text" :class="numberField" :aria-label="`Satuan ${line.name}`" />
                                <span :class="['text-right text-sm font-semibold tabular-nums', color(achievement(numbers[line.claim_id]))]">{{ pct(achievement(numbers[line.claim_id])) }}</span>
                            </div>
                            <div v-else class="grid grid-cols-[3.5rem_3.5rem_5.5rem_4.5rem] items-center gap-1.5 text-center text-sm tabular-nums text-gray-700">
                                <span>{{ line.target ?? '—' }}</span><span>{{ line.realization ?? '—' }}</span>
                                <span class="truncate text-gray-500">{{ line.target_unit ?? '' }}</span>
                                <span :class="['text-right font-semibold', color(line.achievement)]">{{ pct(line.achievement) }}</span>
                            </div>
                        </li>
                    </ul>

                    <!-- PJ text: merged rows share the lead row's text -->
                    <p v-if="groupSize(seg, row) > 1 && !isGroupLead(row)" class="mt-2 text-xs text-gray-500">Parafrase dan catatan mengikuti baris pertama grup.</p>
                    <template v-else-if="canParaphrase(row) && texts[row.row_key]">
                        <textarea
                            v-model="texts[row.row_key].uraian"
                            rows="1"
                            :class="[field, 'mt-2 resize-y border-yellow-300 bg-yellow-50/40']"
                            placeholder="Parafrase PJ — biarkan kosong untuk gunakan uraian di atas"
                            :aria-label="`Parafrase PJ untuk ${row.rk_description}`"
                        />
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <label class="text-xs text-gray-500">Permasalahan
                                <textarea v-model="texts[row.row_key].obstacle" rows="2" :class="[field, 'mt-0.5', texts[row.row_key].obstacle ? 'border-orange-300' : '']" placeholder="—" />
                            </label>
                            <label class="text-xs text-gray-500">Solusi
                                <textarea v-model="texts[row.row_key].solution" rows="2" :class="[field, 'mt-0.5']" placeholder="—" />
                            </label>
                            <label class="text-xs text-gray-500">RTL
                                <textarea v-model="texts[row.row_key].follow_up_plan" rows="2" :class="[field, 'mt-0.5', texts[row.row_key].follow_up_plan ? 'border-orange-300' : '']" placeholder="—" />
                            </label>
                        </div>
                    </template>
                    <div v-else-if="row.pj_uraian || said(row.obstacle) || said(row.solution) || said(row.follow_up_plan)" class="mt-2 space-y-1 text-sm">
                        <p v-if="row.pj_uraian" class="rounded border border-yellow-200 bg-yellow-50/40 px-2 py-1 text-gray-800">{{ row.pj_uraian }}</p>
                        <p v-if="said(row.obstacle) || said(row.solution) || said(row.follow_up_plan)" class="text-xs text-gray-600">
                            <template v-if="said(row.obstacle)">Permasalahan: {{ row.obstacle }}. </template>
                            <template v-if="said(row.solution)">Solusi: {{ row.solution }}. </template>
                            <template v-if="said(row.follow_up_plan)">RTL: {{ row.follow_up_plan }}</template>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <footer v-if="editable" class="flex items-center justify-end gap-3 border-t bg-gray-50 px-4 py-2.5">
            <span v-if="changes" class="text-xs text-gray-500">{{ changes }} perubahan belum disimpan</span>
            <Button size="sm" :disabled="!changes || saving" @click="save">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
        </footer>
    </section>
</template>
