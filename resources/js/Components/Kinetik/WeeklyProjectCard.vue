<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { RecapRow, RecapSegment } from '@/types';
import { Button } from '@/Components/ui/button';
import RecapMergeCell from '@/Components/Kinetik/RecapMergeCell.vue';
import RecapClaimLines from '@/Components/Kinetik/RecapClaimLines.vue';
import RecapTextFields, { type RecapTextForm } from '@/Components/Kinetik/RecapTextFields.vue';
import { achievementColor, numberFormOf, percentLabel, totalsOf, type NumberForm } from '@/composables/useRecapNumbers';
import { groupSize, isGroupLead, textKey, textTarget, type useRecapMerge } from '@/composables/useRecapMerge';
import { ChevronsUpDown, Link2 } from 'lucide-vue-next';

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

type TextForm = RecapTextForm;

const texts = ref<Record<string, TextForm>>({});
const numbers = ref<Record<number, NumberForm>>({});
const saving = ref(false);

// Members write "-" when there is nothing to report.
const said = (v: string | null | undefined) => (v && v.trim() !== '-' ? v : '');

function textOf(row: RecapRow): TextForm {
    return { uraian: row.pj_uraian ?? '', obstacle: said(row.obstacle), solution: said(row.solution), follow_up_plan: said(row.follow_up_plan) };
}

// Fresh forms whenever the server sends new rows (after a save, merge or split).
watch(() => props.seg, (seg) => {
    texts.value = Object.fromEntries(seg.rows.filter(r => !r.merge_key || isGroupLead(r)).map(r => [textKey(r), textOf(r)]));
    numbers.value = Object.fromEntries(seg.rows.flatMap(r => r.claims ?? []).map(c => [c.claim_id, numberFormOf(c)]));
}, { immediate: true });

const same = <T extends object>(a: T, b: T) => JSON.stringify(a) === JSON.stringify(b);

const changedRows = computed(() => props.seg.rows.filter(r => texts.value[textKey(r)] && textKey(r) === r.row_key && !same(texts.value[r.row_key], textOf(r))));
const changedClaims = computed(() => props.seg.rows.flatMap(r => r.claims ?? []).filter(c => !same(numbers.value[c.claim_id], numberFormOf(c))));
const changes = computed(() => changedRows.value.length + changedClaims.value.length);
const editable = computed(() => props.canManage || props.rows.some(props.canParaphrase));


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

/** Consecutive rows of one merge group (the rows arrive group-adjacent, lead first). */
const groups = computed(() => {
    const out: Array<{ key: string; rows: RecapRow[] }> = [];
    for (const row of props.rows) {
        const key = textKey(row);
        const last = out[out.length - 1];
        if (last && last.key === key && groupSize(props.seg, row) > 1) last.rows.push(row);
        else out.push({ key, rows: [row] });
    }
    return out;
});
const leadOf = (rows: RecapRow[]) => rows.find(isGroupLead) ?? rows[0];
const groupTotals = (rows: RecapRow[]) => totalsOf(rows.flatMap(r => r.claims ?? []), numbers.value);
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
            <template v-for="group in groups" :key="group.key">
                <!-- Merged group: one block, one parafrase for all the RK in it -->
                <div v-if="groupSize(seg, group.rows[0]) > 1" class="bg-amber-50/40 px-4 py-3 ring-1 ring-inset ring-amber-200" role="group" :aria-label="`Gabungan ${group.rows.length} RK`">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-amber-700"><Link2 class="h-3 w-3" aria-hidden="true" /> Gabungan {{ groupSize(seg, group.rows[0]) }} RK</p>
                            <ul class="mt-0.5 list-disc pl-4 text-sm font-medium leading-snug text-gray-800">
                                <li v-for="row in group.rows" :key="row.row_key">{{ row.rk_description }}</li>
                            </ul>
                        </div>
                        <button v-if="canManage" type="button" class="text-xs text-gray-500 underline hover:text-red-600 focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-50" :disabled="merge.busy.value" @click="merge.split(leadOf(group.rows))">Batalkan penggabungan</button>
                    </div>

                    <div class="mt-2 space-y-2">
                        <RecapClaimLines v-for="row in group.rows" :key="row.row_key" :claims="row.claims ?? []" :numbers="numbers" :can-edit="canManage" />
                    </div>

                    <p v-for="total in [groupTotals(group.rows)]" :key="group.key" class="mt-2 flex items-baseline justify-end gap-2 text-sm tabular-nums text-gray-700">
                        <span class="text-xs text-gray-500">Total gabungan</span>
                        <span>{{ total.realization }} / {{ total.target }} {{ total.unit }}</span>
                        <span :class="['font-semibold', achievementColor(total.achievement)]">{{ percentLabel(total.achievement) }}</span>
                    </p>

                    <div class="mt-2">
                        <RecapTextFields v-if="canParaphrase(leadOf(group.rows)) && texts[textKey(group.rows[0])]" :form="texts[textKey(group.rows[0])]" merged :label="`gabungan ${group.rows.length} RK`" />
                        <div v-else-if="leadOf(group.rows).pj_uraian || said(leadOf(group.rows).obstacle) || said(leadOf(group.rows).solution) || said(leadOf(group.rows).follow_up_plan)" class="space-y-1 text-sm">
                            <p v-if="leadOf(group.rows).pj_uraian" class="rounded border border-amber-200 bg-white px-2 py-1 text-gray-800">{{ leadOf(group.rows).pj_uraian }}</p>
                            <p class="text-xs text-gray-600">
                                <template v-if="said(leadOf(group.rows).obstacle)">Permasalahan: {{ leadOf(group.rows).obstacle }}. </template>
                                <template v-if="said(leadOf(group.rows).solution)">Solusi: {{ leadOf(group.rows).solution }}. </template>
                                <template v-if="said(leadOf(group.rows).follow_up_plan)">RTL: {{ leadOf(group.rows).follow_up_plan }}</template>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Single row -->
                <div v-else class="px-4 py-3">
                    <RecapMergeCell
                        :row="group.rows[0]"
                        :size="1"
                        :lead="false"
                        :selectable="canManage"
                        :selected="merge.isSelected(seg, group.rows[0])"
                        :busy="merge.busy.value"
                        @toggle="merge.toggle(seg, group.rows[0])"
                    >
                        <p class="text-sm font-medium leading-snug text-gray-800">{{ group.rows[0].rk_description }}</p>
                    </RecapMergeCell>

                    <div :class="canManage ? 'md:pl-[1.625rem]' : ''">
                        <div class="mt-2"><RecapClaimLines :claims="group.rows[0].claims ?? []" :numbers="numbers" :can-edit="canManage" /></div>

                        <div class="mt-2">
                            <RecapTextFields v-if="canParaphrase(group.rows[0]) && texts[group.rows[0].row_key]" :form="texts[group.rows[0].row_key]" :label="group.rows[0].rk_description" />
                            <div v-else-if="group.rows[0].pj_uraian || said(group.rows[0].obstacle) || said(group.rows[0].solution) || said(group.rows[0].follow_up_plan)" class="space-y-1 text-sm">
                                <p v-if="group.rows[0].pj_uraian" class="rounded border border-yellow-200 bg-yellow-50/40 px-2 py-1 text-gray-800">{{ group.rows[0].pj_uraian }}</p>
                                <p class="text-xs text-gray-600">
                                    <template v-if="said(group.rows[0].obstacle)">Permasalahan: {{ group.rows[0].obstacle }}. </template>
                                    <template v-if="said(group.rows[0].solution)">Solusi: {{ group.rows[0].solution }}. </template>
                                    <template v-if="said(group.rows[0].follow_up_plan)">RTL: {{ group.rows[0].follow_up_plan }}</template>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <footer v-if="editable" class="flex items-center justify-end gap-3 border-t bg-gray-50 px-4 py-2.5">
            <span v-if="changes" class="text-xs text-gray-500">{{ changes }} perubahan belum disimpan</span>
            <Button size="sm" :disabled="!changes || saving" @click="save">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
        </footer>
    </section>
</template>
