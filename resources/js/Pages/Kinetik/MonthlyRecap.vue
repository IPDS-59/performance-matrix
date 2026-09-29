<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecapLockBar from '@/Components/Kinetik/RecapLockBar.vue';
import RecapToolbar from '@/Components/Kinetik/RecapToolbar.vue';
import PrefillButton from '@/Components/Kinetik/PrefillButton.vue';
import RecapMergeCell from '@/Components/Kinetik/RecapMergeCell.vue';
import { groupAdjacent, groupSize, isGroupLead, textKey, textTarget, useRecapMerge } from '@/composables/useRecapMerge';
import MeetingChecklist from '@/Components/Kinetik/MeetingChecklist.vue';
import { periodChecklist } from '@/composables/useMeetingChecklist';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RecapSegment, RecapRow, TeamOption, RecapLockState } from '@/types';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronDown, ChevronUp, Check, ChevronsUpDown } from 'lucide-vue-next';
import { useRecapExport } from '@/composables/useRecapExport';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    year: number;
    month: number;
    canManage: boolean;
    canLock: boolean;
    lock: RecapLockState | null;
    currentEmployeeId: number | null;
}>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const monthLabel = computed(() => `${MONTHS[props.month - 1]} ${props.year}`);

const { exporting, download } = useRecapExport();

const checklist = computed(() => periodChecklist({ segments: props.segments, lock: props.lock }));

// ── Navigation ─────────────────────────────────────────────────────────────

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.monthly'), {
        team: props.selectedTeamId ?? undefined,
        year: props.year,
        month: props.month,
        ...params,
    }, { preserveState: false });
}

function prevMonth() {
    const d = new Date(props.year, props.month - 2, 1);
    navigate({ year: d.getFullYear(), month: d.getMonth() + 1 });
}

function nextMonth() {
    const d = new Date(props.year, props.month, 1);
    navigate({ year: d.getFullYear(), month: d.getMonth() + 1 });
}

// ── Achievement color ──────────────────────────────────────────────────────

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600 font-semibold';
    if (n >= 50) return 'text-yellow-600 font-semibold';
    return 'text-red-600 font-semibold';
}

// ── Per-segment sort ───────────────────────────────────────────────────────

const sortDirs = ref<Record<string, 'asc' | 'desc'>>({});

function sortDir(segKey: string): 'asc' | 'desc' {
    return sortDirs.value[segKey] ?? 'asc';
}

function toggleSort(segKey: string) {
    sortDirs.value[segKey] = sortDir(segKey) === 'asc' ? 'desc' : 'asc';
}

// ── "Perlu perhatian" filter ───────────────────────────────────────────────

const attentionOnly = ref(false);

function needsAttention(row: RecapRow): boolean {
    const hasObstacle = !!row.obstacle_aggregated && row.obstacle_aggregated !== '—' && row.obstacle_aggregated !== 'N/A';
    return (row.achievement ?? 0) < 100 || hasObstacle || !row.is_confirmed;
}

function attentionCount(seg: RecapSegment): number {
    return seg.rows.filter(needsAttention).length;
}

function filteredRows(seg: RecapSegment): RecapRow[] {
    const base = attentionOnly.value ? seg.rows.filter(needsAttention) : seg.rows;
    const key = String(seg.project_id ?? 'none');
    const dir = sortDir(key);
    return groupAdjacent([...base].sort((a, b) =>
        dir === 'asc'
            ? (a.achievement ?? 0) - (b.achievement ?? 0)
            : (b.achievement ?? 0) - (a.achievement ?? 0),
    ));
}

// ── Gabungkan / Pisahkan ───────────────────────────────────────────────────

const rowMerge = useRecapMerge(
    () => ({ team_id: props.selectedTeamId, period_type: 'month', period_year: props.year, period_month: props.month }),
    () => { paraForms.value = {}; },
);

// ── Bulk confirm ───────────────────────────────────────────────────────────

const bulkConfirmIds = computed(() =>
    props.segments
        .flatMap((seg) => seg.rows)
        .filter((row) => (row.achievement ?? 0) >= 100 && !row.is_confirmed),
);

const bulkConfirming = ref(false);

function confirmBulk() {
    if (!bulkConfirmIds.value.length) return;
    bulkConfirming.value = true;
    router.post(route('team-recap.override.confirm-bulk'), {
        team_id: props.selectedTeamId,
        period_type: 'month',
        period_year: props.year,
        period_month: props.month,
        performance_plan_ids: bulkConfirmIds.value.map((row) => row.performance_plan_id),
        project_ids: bulkConfirmIds.value.map((row) => row.project_id),
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { bulkConfirming.value = false; },
    });
}

// ── Expand state ───────────────────────────────────────────────────────────

const expandedRows = ref<Record<string, boolean>>({});

function toggleExpand(key: string) {
    expandedRows.value[key] = !expandedRows.value[key];
}

// ── Confirmation ───────────────────────────────────────────────────────────

function toggleConfirm(row: RecapRow) {
    router.post(route('team-recap.override.confirm'), {
        team_id: props.selectedTeamId,
        ...textTarget(row),
        period_type: 'month',
        period_year: props.year,
        period_month: props.month,
        confirmed: !row.is_confirmed,
    }, { preserveScroll: true, preserveState: true });
}

// ── Per-row paraphrase permission ──────────────────────────────────────────

function rowCanParaphrase(row: RecapRow): boolean {
    if (props.lock) return false;
    return props.canManage || (props.currentEmployeeId !== null && row.pic_employee_id === props.currentEmployeeId);
}

// ── Paraphrase forms (per planId) ──────────────────────────────────────────

type SeedSource = 'pj' | 'inherited' | 'agg' | 'none';
type ParaForm = { obstacle: string; solution: string; follow_up_plan: string; uraian: string;
    saving: boolean; seedSource: SeedSource };
const paraForms = ref<Record<string, ParaForm>>({});

function getParaForm(row: RecapRow): ParaForm {
    if (!paraForms.value[textKey(row)]) {
        const hasPj = row.pj_obstacle !== null && row.pj_obstacle !== '';
        const hasInherited = !!row.inherited_obstacle;

        let seedSource: SeedSource;
        let obstacleInit: string;
        let solutionInit: string;
        let followUpInit: string;

        if (hasPj) {
            seedSource = 'pj';
            obstacleInit = row.pj_obstacle!;
            solutionInit = row.pj_solution ?? '';
            followUpInit = row.pj_follow_up_plan ?? '';
        } else if (hasInherited) {
            seedSource = 'inherited';
            obstacleInit = row.inherited_obstacle!;
            solutionInit = row.inherited_solution ?? '';
            followUpInit = row.inherited_follow_up_plan ?? '';
        } else if (row.obstacle_aggregated) {
            seedSource = 'agg';
            obstacleInit = row.obstacle_aggregated;
            solutionInit = '';
            followUpInit = '';
        } else {
            seedSource = 'none';
            obstacleInit = '';
            solutionInit = '';
            followUpInit = '';
        }

        paraForms.value[textKey(row)] = {
            uraian: row.pj_uraian ?? '',
            obstacle: obstacleInit,
            solution: solutionInit,
            follow_up_plan: followUpInit,
            saving: false,
            seedSource,
        };
    }

    return paraForms.value[textKey(row)];
}

function pullFromInherited(row: RecapRow) {
    const f = getParaForm(row);
    f.obstacle = row.inherited_obstacle ?? '';
    f.solution = row.inherited_solution ?? '';
    f.follow_up_plan = row.inherited_follow_up_plan ?? '';
}

function saveParaphrase(row: RecapRow) {
    const f = getParaForm(row);
    f.saving = true;
    router.post(route('team-recap.override.store'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        project_id: row.project_id,
        period_type: 'month',
        period_year: props.year,
        period_month: props.month,
        uraian: f.uraian,
        obstacle: f.obstacle,
        solution: f.solution,
        follow_up_plan: f.follow_up_plan,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { f.saving = false; },
    });
}
</script>

<template>
    <Head title="Rekap Bulanan" />
    <AppLayout>
        <template #title>Rekap Tim (Bulanan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <RecapToolbar
                v-model:attention-only="attentionOnly"
                :teams="teams"
                :selected-team-id="selectedTeamId"
                :period-label="monthLabel"
                prev-label="Bulan sebelumnya"
                next-label="Bulan berikutnya"
                :attention-total="segments.reduce((sum, seg) => sum + attentionCount(seg), 0)"
                :exporting="exporting"
                @change-team="navigate({ team: $event })"
                @prev="prevMonth()"
                @next="nextMonth()"
                @export="download({ period_type: 'month', year, month }, `Rapat Bulanan ${monthLabel}.xlsx`)"
            >
                <template #actions>
                    <PrefillButton
                        v-if="canManage && selectedTeamId"
                        source="mingguan"
                        :payload="{ team_id: selectedTeamId, period_type: 'month', period_year: year, period_month: month }"
                        @done="paraForms = {}"
                    />
                    <Button
                        v-if="canManage"
                        size="sm"
                        variant="outline"
                        :disabled="!bulkConfirmIds.length || bulkConfirming"
                        title="Tandai semua baris dengan capaian 100% sebagai terkonfirmasi"
                        @click="confirmBulk"
                    >
                        <Check class="mr-1.5 h-4 w-4 text-green-600" />
                        Konfirmasi semua capaian 100%
                        <span v-if="bulkConfirmIds.length" class="ml-1.5 rounded-full bg-green-100 px-1.5 text-[11px] text-green-700 tabular-nums">{{ bulkConfirmIds.length }}</span>
                    </Button>
                </template>
            </RecapToolbar>

            <MeetingChecklist v-if="selectedTeamId" :steps="checklist" />

            <RecapLockBar :team-id="selectedTeamId" :lock="lock" :can-lock="canLock" :period="{ period_type: 'month', period_year: year, period_month: month }" />

            <!-- Segments by project -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap tersimpan untuk tim ini pada bulan ini.
            </div>

            <div v-else id="rekap-baris" class="scroll-mt-4 space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="flex items-center justify-between gap-3 border-b bg-gray-50 px-4 py-3">
                        <h3 class="min-w-0 text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                        <Button
                            v-if="canManage && rowMerge.selectedCount(seg) >= 2"
                            size="sm"
                            class="ml-auto h-7 px-2.5 text-xs"
                            :disabled="rowMerge.busy.value"
                            @click="rowMerge.merge(seg, filteredRows(seg))"
                        >
                            Gabungkan {{ rowMerge.selectedCount(seg) }} baris
                        </Button>
                        <span v-if="attentionCount(seg) > 0" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                            {{ attentionCount(seg) }} perlu perhatian
                        </span>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="min-w-[13rem] text-left sm:min-w-[18rem]">Rencana Kinerja</TableHead>
                                <TableHead class="hidden text-left text-xs md:table-cell">Kontributor</TableHead>
                                <TableHead class="text-right">Target</TableHead>
                                <TableHead class="text-right">Realisasi</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort(String(seg.project_id ?? 'none'))">
                                    <span class="inline-flex items-center gap-1">
                                        Capaian
                                        <ChevronsUpDown class="h-3 w-3" :class="{ 'rotate-180': sortDir(String(seg.project_id ?? 'none')) === 'desc' }" />
                                    </span>
                                </TableHead>
                                <TableHead class="text-center">Konfirmasi</TableHead>
                                <TableHead class="w-8" />
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <template v-for="row in filteredRows(seg)" :key="row.row_key">
                                <TableRow :class="['hover:bg-gray-50', groupSize(seg, row) > 1 ? 'border-l-2 border-l-primary/60' : '']">
                                    <TableCell class="min-w-[13rem] whitespace-normal align-top sm:min-w-[18rem]">
                                        <RecapMergeCell
                                            :row="row"
                                            :size="groupSize(seg, row)"
                                            :lead="isGroupLead(row)"
                                            :selectable="canManage"
                                            :selected="rowMerge.isSelected(seg, row)"
                                            :busy="rowMerge.busy.value"
                                            @toggle="rowMerge.toggle(seg, row)"
                                            @split="rowMerge.split(row)"
                                        >
                                            <p class="font-medium leading-snug text-gray-800">{{ row.rk_description }}</p>
                                            <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                            <p v-if="row.is_overridden" class="mt-0.5 text-xs italic text-blue-500">Telah diparafrase</p>
                                        </RecapMergeCell>
                                    </TableCell>
                                    <TableCell class="hidden min-w-[10rem] max-w-[16rem] whitespace-normal align-top md:table-cell text-xs leading-snug text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top tabular-nums text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top tabular-nums text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top tabular-nums">
                                        <span v-if="row.achievement != null" :class="achievementColor(row.achievement)">{{ row.achievement.toFixed(2) }}%</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <template v-if="canManage">
                                            <button
                                                type="button"
                                                :class="['inline-flex h-5 w-5 items-center justify-center rounded border-2 transition-colors', row.is_confirmed ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 hover:border-green-400']"
                                                :title="row.is_confirmed ? `Dikonfirmasi oleh ${row.confirmed_by ?? 'PJ'}` : 'Klik untuk konfirmasi'"
                                                @click="toggleConfirm(row)"
                                            >
                                                <Check v-if="row.is_confirmed" class="h-3 w-3" />
                                            </button>
                                            <p v-if="row.is_confirmed && row.confirmed_by" class="mt-0.5 text-xs text-green-600">{{ row.confirmed_by }}</p>
                                        </template>
                                        <template v-else>
                                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', row.is_confirmed ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                                {{ row.is_confirmed ? 'Terkonfirmasi' : 'Belum' }}
                                            </span>
                                        </template>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded hover:bg-gray-100"
                                            :title="expandedRows[row.row_key] ? 'Tutup panel' : 'Buka panel parafrase'"
                                            @click="toggleExpand(row.row_key)"
                                        >
                                            <ChevronDown v-if="!expandedRows[row.row_key]" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <!-- Expand panel -->
                                <TableRow v-if="expandedRows[row.row_key]" :key="`${row.row_key}-panel`" class="bg-gray-50">
                                    <TableCell colspan="8" class="whitespace-normal px-4 py-4 sm:px-6">
                                        <div class="space-y-4">
                                            <!-- Member kendala (read-only) -->
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala (anggota)</p>
                                                <p class="rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.obstacle_aggregated || '—' }}</p>
                                            </div>

                                            <!-- Paraphrase inputs (PJ or this row's PIC) -->
                                            <template v-if="rowCanParaphrase(row)">
                                                <div>
                                                    <Label class="text-xs">Uraian (PJ)</Label>
                                                    <Textarea v-model="getParaForm(row).uraian" :rows="3" class="mt-1 text-sm" placeholder="Kosongkan untuk memakai uraian kegiatan anggota di Excel" />
                                                </div>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <Label class="text-xs">Kendala (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).obstacle" :rows="2" class="mt-1 text-sm" />
                                                        <p v-if="getParaForm(row).seedSource === 'inherited'" class="mt-0.5 text-xs italic text-gray-400">Dirangkum dari parafrase mingguan</p>
                                                        <p v-else-if="getParaForm(row).seedSource === 'agg'" class="mt-0.5 text-xs italic text-gray-400">Prafilled dari kendala anggota</p>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Solusi (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).solution" :rows="2" class="mt-1 text-sm" />
                                                        <p v-if="getParaForm(row).seedSource === 'inherited'" class="mt-0.5 text-xs italic text-gray-400">Dirangkum dari parafrase mingguan</p>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">RTL (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).follow_up_plan" :rows="2" class="mt-1 text-sm" />
                                                        <p v-if="getParaForm(row).seedSource === 'inherited'" class="mt-0.5 text-xs italic text-gray-400">Dirangkum dari parafrase mingguan</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        :disabled="!row.inherited_obstacle && !row.inherited_solution && !row.inherited_follow_up_plan"
                                                        @click="pullFromInherited(row)"
                                                    >
                                                        Tarik dari mingguan
                                                    </Button>
                                                    <Button size="sm" :disabled="getParaForm(row).saving" @click="saveParaphrase(row)">
                                                        Simpan parafrase
                                                    </Button>
                                                </div>
                                            </template>

                                            <!-- Read-only paraphrase (no paraphrase permission) -->
                                            <template v-else>
                                                <div v-if="row.pj_uraian">
                                                    <p class="mb-1 text-xs font-medium text-gray-500">Uraian (PJ)</p>
                                                    <p class="whitespace-pre-line text-sm text-gray-700">{{ row.pj_uraian }}</p>
                                                </div>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Kendala (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_obstacle || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Solusi (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_solution || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">RTL (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_follow_up_plan || '—' }}</p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
