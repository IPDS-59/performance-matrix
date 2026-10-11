<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { WeeklyRow, WeeklyRowLine, WeeklySegment } from '@/types';
import { Button } from '@/Components/ui/button';
import { achievementColor, achievementOf, percentLabel } from '@/composables/useRecapNumbers';
import { AlertTriangle } from 'lucide-vue-next';

/**
 * One Projek of the weekly team recap, as in the "rekap per projek" mockup:
 * a row per kegiatan (or one merged row), each with the PJ's parafrase and
 * Permasalahan / Solusi / RTL. One Simpan per Projek saves the rows into
 * "Laporan Tersimpan". Members' own kendala and solusi are already in the
 * boxes, so the PJ only edits what needs a different wording.
 */
const props = defineProps<{
    seg: WeeklySegment;
    rows: WeeklyRow[];
    /** The PJ, and the week is not locked. */
    canManage: boolean;
    canParaphrase: (row: WeeklyRow) => boolean;
    attention: number;
    /** team_id and week_start for the team-recap.* requests. */
    payload: { team_id: number | null; week_start: string };
}>();

type TextForm = { uraian: string; obstacle: string; solution: string; follow_up_plan: string };
type NumberForm = { target: string; realization: string; target_unit: string };

const texts = ref<Record<string, TextForm>>({});
const numbers = ref<Record<number, NumberForm>>({});
const selected = ref<string[]>([]);
const saving = ref(false);
const busy = ref(false);

const str = (v: number | null) => (v == null ? '' : String(v));
const lineNumbers = (c: WeeklyRowLine): NumberForm => ({ target: str(c.target), realization: str(c.realization), target_unit: c.target_unit ?? '' });
const textOf = (row: WeeklyRow): TextForm => ({
    // A merged row starts from the first member's uraian, as in the mockup.
    uraian: row.pj_uraian ?? (row.merged ? (row.claims[0]?.uraian ?? '') : ''),
    obstacle: row.obstacle ?? '', solution: row.solution ?? '', follow_up_plan: row.follow_up_plan ?? '',
});

// Fresh forms whenever the server sends new rows (after a save, merge or split).
watch(() => props.seg, (seg) => {
    texts.value = Object.fromEntries(seg.rows.map(r => [r.key, textOf(r)]));
    numbers.value = Object.fromEntries(seg.rows.flatMap(r => r.claims).map(c => [c.claim_id, lineNumbers(c)]));
    selected.value = [];
}, { immediate: true });

const same = (a: object, b: object) => JSON.stringify(a) === JSON.stringify(b);
const changedClaims = computed(() => props.seg.rows.flatMap(r => r.claims).filter(c => !same(numbers.value[c.claim_id], lineNumbers(c))));
const changedRows = computed(() => props.seg.rows.filter(r => texts.value[r.key] && !same(texts.value[r.key], textOf(r))));
const unsaved = computed(() => props.seg.rows.filter(r => !r.saved).length);
const editable = computed(() => props.seg.rows.some(props.canParaphrase));
const canSave = computed(() => editable.value && (changedClaims.value.length > 0 || changedRows.value.length > 0 || unsaved.value > 0));

// ── Numbers: a merged row shows the totals of its kegiatan ─────────────────
type Field = keyof NumberForm;
const toNum = (v: string) => Number(v) || 0;

function total(row: WeeklyRow, field: 'target' | 'realization'): number {
    return row.claims.reduce((n, c) => n + toNum(numbers.value[c.claim_id]?.[field] ?? ''), 0);
}
function unitOf(row: WeeklyRow): string {
    return row.claims.map(c => numbers.value[c.claim_id]?.target_unit).find(Boolean) ?? '';
}
/** Editing a total changes the first kegiatan by the difference, so every other page still adds up from the claims. */
function setTotal(row: WeeklyRow, field: Field, value: string) {
    const [lead, ...rest] = row.claims;
    if (field === 'target_unit') { numbers.value[lead.claim_id].target_unit = value; return; }
    const others = rest.reduce((n, c) => n + toNum(numbers.value[c.claim_id][field]), 0);
    numbers.value[lead.claim_id][field] = String(Math.max(0, toNum(value) - others));
}
function rowAchievementOf(row: WeeklyRow): number | null {
    return achievementOf(total(row, 'target'), total(row, 'realization'));
}

// ── Merge ──────────────────────────────────────────────────────────────────
const selectable = (row: WeeklyRow) => props.canManage && !row.merged;
const toggle = (row: WeeklyRow) => { selected.value = selected.value.includes(row.key) ? selected.value.filter(k => k !== row.key) : [...selected.value, row.key]; };

function merge() {
    const claimIds = props.rows.filter(r => selected.value.includes(r.key)).flatMap(r => r.claim_ids);
    if (selected.value.length < 2) return;
    busy.value = true;
    router.post(route('team-recap.weekly-rows.merge'), { ...props.payload, claim_ids: claimIds }, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
function split(row: WeeklyRow) {
    busy.value = true;
    router.post(route('team-recap.weekly-rows.split'), { ...props.payload, row_id: row.row_id }, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

function save() {
    saving.value = true;
    router.post(route('team-recap.weekly-project'), {
        ...props.payload,
        rows: props.seg.rows.filter(props.canParaphrase).map(r => ({ claim_ids: r.claim_ids, uraian: texts.value[r.key].uraian, obstacle: texts.value[r.key].obstacle, solution: texts.value[r.key].solution, follow_up_plan: texts.value[r.key].follow_up_plan })),
        claims: changedClaims.value.map(c => ({ id: c.claim_id, target: numbers.value[c.claim_id].target || null, realization: numbers.value[c.claim_id].realization || null, target_unit: numbers.value[c.claim_id].target_unit || null })),
    }, { preserveScroll: true, onFinish: () => (saving.value = false) });
}

// Member badge colours, stable per name.
const palette = ['bg-indigo-100 text-indigo-700', 'bg-purple-100 text-purple-700', 'bg-teal-100 text-teal-700', 'bg-orange-100 text-orange-700', 'bg-violet-100 text-violet-700', 'bg-cyan-100 text-cyan-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700'];
const badge = (name: string) => palette[[...name].reduce((n, ch) => n + ch.charCodeAt(0), 0) % palette.length];
const shortName = (name: string) => name.split(/[ ,]/)[0];
const memberNames = (row: WeeklyRow) => [...new Set(row.claims.map(c => c.name))];

const claimCount = computed(() => props.seg.rows.reduce((n, r) => n + r.claims.length, 0));
const field = 'w-full rounded border bg-white px-2 py-1.5 text-xs placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
const box = 'h-7 w-full rounded border border-gray-300 bg-white px-1 text-center text-sm font-semibold text-gray-700 tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
const cols = 'md:grid-cols-[2rem_8rem_1fr_3.25rem_4rem_4.5rem_3.5rem]';
</script>

<template>
    <section class="overflow-hidden rounded-md border bg-white" :aria-label="`Projek ${seg.project_name}`">
        <header class="flex items-start justify-between gap-3 border-b bg-gray-50 px-4 py-3">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Projek</p>
                <h3 class="text-sm font-bold text-gray-900">{{ seg.project_name }}</h3>
                <p v-if="seg.leader_rk" class="mt-0.5 text-[11px] text-indigo-500">RK Ketua: {{ seg.leader_rk }}</p>
            </div>
            <p class="shrink-0 text-[11px] text-gray-400">
                {{ claimCount }} klaim<template v-if="canManage"> · PJ pilih mana yang digabung</template>
                <span v-if="attention > 0" class="ml-2 rounded-full bg-orange-100 px-2 py-0.5 font-medium text-orange-700">{{ attention }} perlu perhatian</span>
            </p>
        </header>

        <div v-if="canManage" class="flex items-center justify-between gap-3 border-b border-indigo-100 bg-indigo-50/40 px-4 py-2.5">
            <span class="text-xs font-medium text-indigo-700">Centang baris yang ingin digabung, lalu klik tombol gabung</span>
            <button
                type="button"
                :disabled="selected.length < 2 || busy"
                :class="['rounded px-3 py-1.5 text-xs font-medium transition-colors', selected.length >= 2 ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'cursor-not-allowed bg-gray-100 text-gray-400']"
                @click="merge"
            >
                {{ selected.length >= 2 ? `Gabungkan ${selected.length} baris yang dipilih` : 'Gabungkan yang dipilih' }}
            </button>
        </div>

        <div :class="['hidden gap-x-1 border-b bg-gray-50/60 px-4 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400 md:grid', cols]">
            <span /><span>Anggota</span><span>Uraian dari kipApp</span><span class="text-center">Target</span><span class="text-center">Realisasi</span><span class="text-center">Satuan</span><span class="text-right">Capaian</span>
        </div>

        <div class="divide-y divide-gray-100">
            <div v-for="row in rows" :key="row.key" :class="row.merged ? 'bg-amber-50/30' : ''">
                <div :class="['grid items-start gap-x-1 gap-y-1 px-4 pb-2 pt-3', cols]">
                    <div class="pt-0.5">
                        <input
                            v-if="selectable(row)"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus-visible:ring-2 focus-visible:ring-primary"
                            :checked="selected.includes(row.key)"
                            :aria-label="`Pilih untuk digabungkan: ${row.claims[0].uraian ?? row.claims[0].rk_description}`"
                            @change="toggle(row)"
                        />
                    </div>

                    <!-- Anggota -->
                    <div class="flex flex-col items-start gap-1 pr-1">
                        <span v-for="name in memberNames(row)" :key="name" :class="['w-fit rounded-full px-2 py-0.5 text-[10px] font-medium', badge(name)]" :title="name">{{ shortName(name) }}</span>
                        <span v-if="row.merged" class="text-[10px] font-bold uppercase tracking-wide text-amber-600">Gabungan</span>
                        <span v-else-if="(rowAchievementOf(row) ?? 100) < 100" class="flex items-center gap-0.5 text-[10px] font-medium text-orange-500"><AlertTriangle class="h-3 w-3" aria-hidden="true" />{{ percentLabel(rowAchievementOf(row)) }}</span>
                    </div>

                    <!-- Uraian dari kipApp -->
                    <div class="min-w-0 pr-3">
                        <template v-if="row.merged">
                            <div class="space-y-0.5 font-mono text-[10px] leading-snug text-gray-400">
                                <div v-for="line in row.claims" :key="line.claim_id">{{ shortName(line.name) }}: "{{ line.uraian ?? 'Tanpa uraian' }}"</div>
                            </div>
                        </template>
                        <template v-else>
                            <p class="font-mono text-[11px] leading-snug text-gray-600">{{ row.claims[0].uraian ?? 'Tanpa uraian' }}</p>
                            <p class="text-[10px] text-indigo-500">RK: {{ row.claims[0].rk_description }}</p>
                            <p v-if="row.claims[0].adjusted_by" class="text-[10px] text-amber-700">Angka dikoreksi {{ row.claims[0].adjusted_by }}</p>
                        </template>
                    </div>

                    <!-- Target / Realisasi / Satuan / Capaian: totals for a merged row -->
                    <template v-if="canManage && numbers[row.claims[0].claim_id]">
                        <input :value="row.merged ? total(row, 'target') : numbers[row.claims[0].claim_id].target" type="number" min="0" step="any" :class="box" :aria-label="`Target ${row.merged ? 'gabungan' : row.claims[0].name}`" @input="row.merged ? setTotal(row, 'target', ($event.target as HTMLInputElement).value) : (numbers[row.claims[0].claim_id].target = ($event.target as HTMLInputElement).value)" />
                        <input :value="row.merged ? total(row, 'realization') : numbers[row.claims[0].claim_id].realization" type="number" min="0" step="any" :class="box" :aria-label="`Realisasi ${row.merged ? 'gabungan' : row.claims[0].name}`" @input="row.merged ? setTotal(row, 'realization', ($event.target as HTMLInputElement).value) : (numbers[row.claims[0].claim_id].realization = ($event.target as HTMLInputElement).value)" />
                        <input :value="row.merged ? unitOf(row) : numbers[row.claims[0].claim_id].target_unit" type="text" placeholder="satuan" :class="box" :aria-label="`Satuan ${row.merged ? 'gabungan' : row.claims[0].name}`" @input="setTotal(row, 'target_unit', ($event.target as HTMLInputElement).value)" />
                    </template>
                    <template v-else>
                        <span class="text-center text-sm font-semibold tabular-nums text-gray-700">{{ total(row, 'target') }}</span>
                        <span class="text-center text-sm font-semibold tabular-nums text-gray-700">{{ total(row, 'realization') }}</span>
                        <span class="truncate text-center text-sm text-gray-500">{{ unitOf(row) }}</span>
                    </template>
                    <span :class="['pt-1 text-right text-sm font-semibold tabular-nums', achievementColor(rowAchievementOf(row))]">{{ percentLabel(rowAchievementOf(row)) }}</span>
                </div>

                <!-- PJ text -->
                <div class="px-4 pb-3">
                    <template v-if="canParaphrase(row) && texts[row.key]">
                        <textarea
                            v-model="texts[row.key].uraian"
                            :rows="row.merged ? 2 : 1"
                            :class="[field, 'resize-y', row.merged ? 'border-amber-300' : 'border-yellow-300 bg-yellow-50/40']"
                            :placeholder="row.merged ? 'Tulis parafrase gabungan…' : 'Parafrase PJ — biarkan kosong untuk gunakan uraian di atas'"
                            :aria-label="`${row.merged ? 'Parafrase gabungan' : 'Parafrase PJ'} untuk ${row.claims[0].uraian ?? row.claims[0].rk_description}`"
                        />
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <label class="text-[10px] font-medium text-gray-500">Permasalahan
                                <textarea v-model="texts[row.key].obstacle" rows="2" :class="[field, 'mt-1 border-gray-200', texts[row.key].obstacle ? 'border-orange-300' : '']" placeholder="—" />
                            </label>
                            <label class="text-[10px] font-medium text-gray-500">Solusi
                                <textarea v-model="texts[row.key].solution" rows="2" :class="[field, 'mt-1 border-gray-200']" placeholder="—" />
                            </label>
                            <label class="text-[10px] font-medium text-gray-500">RTL
                                <textarea v-model="texts[row.key].follow_up_plan" rows="2" :class="[field, 'mt-1 border-gray-200', texts[row.key].follow_up_plan ? 'border-orange-300' : '']" placeholder="—" />
                            </label>
                        </div>
                    </template>
                    <div v-else-if="row.pj_uraian || row.obstacle || row.solution || row.follow_up_plan" class="space-y-1 text-sm">
                        <p v-if="row.pj_uraian" class="rounded border border-yellow-200 bg-yellow-50/40 px-2 py-1 text-gray-800">{{ row.pj_uraian }}</p>
                        <p class="text-xs text-gray-600">
                            <template v-if="row.obstacle">Permasalahan: {{ row.obstacle }}. </template>
                            <template v-if="row.solution">Solusi: {{ row.solution }}. </template>
                            <template v-if="row.follow_up_plan">RTL: {{ row.follow_up_plan }}</template>
                        </p>
                    </div>
                    <div v-if="row.merged && canManage" class="mt-1 flex justify-end">
                        <button type="button" class="text-[10px] text-gray-400 underline hover:text-red-500 focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-50" :disabled="busy" @click="split(row)">Batalkan penggabungan</button>
                    </div>
                </div>
            </div>
        </div>

        <footer v-if="editable" class="flex items-center justify-end gap-3 border-t bg-gray-50/60 px-4 py-3">
            <span v-if="changedClaims.length + changedRows.length" class="text-xs text-gray-500">{{ changedClaims.length + changedRows.length }} perubahan belum disimpan</span>
            <span v-else-if="unsaved" class="text-xs text-gray-500">{{ unsaved }} baris belum masuk Laporan Tersimpan</span>
            <Button size="sm" class="bg-gray-800 hover:bg-gray-900" :disabled="!canSave || saving" @click="save">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
        </footer>
    </section>
</template>
