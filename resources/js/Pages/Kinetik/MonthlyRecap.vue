<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RecapSegment, RecapRow, TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronLeft, ChevronRight, ChevronDown, ChevronUp, Check, ChevronsUpDown } from 'lucide-vue-next';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    year: number;
    month: number;
    canManage: boolean;
}>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const monthLabel = computed(() => `${MONTHS[props.month - 1]} ${props.year}`);

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
    return [...base].sort((a, b) =>
        dir === 'asc'
            ? (a.achievement ?? 0) - (b.achievement ?? 0)
            : (b.achievement ?? 0) - (a.achievement ?? 0),
    );
}

// ── Bulk confirm ───────────────────────────────────────────────────────────

const bulkConfirmIds = computed(() =>
    props.segments
        .flatMap((seg) => seg.rows)
        .filter((row) => (row.achievement ?? 0) >= 100 && !row.is_confirmed)
        .map((row) => row.performance_plan_id),
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
        performance_plan_ids: bulkConfirmIds.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { bulkConfirming.value = false; },
    });
}

// ── Expand state ───────────────────────────────────────────────────────────

const expandedRows = ref<Record<number, boolean>>({});

function toggleExpand(planId: number) {
    expandedRows.value[planId] = !expandedRows.value[planId];
}

// ── Confirmation ───────────────────────────────────────────────────────────

function toggleConfirm(row: RecapRow) {
    router.post(route('team-recap.override.confirm'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'month',
        period_year: props.year,
        period_month: props.month,
        confirmed: !row.is_confirmed,
    }, { preserveScroll: true, preserveState: true });
}

// ── Paraphrase forms (per planId) ──────────────────────────────────────────

type ParaForm = { obstacle: string; solution: string; follow_up_plan: string; saving: boolean; seededFromAgg: boolean };
const paraForms = ref<Record<number, ParaForm>>({});

function getParaForm(row: RecapRow): ParaForm {
    if (!paraForms.value[row.performance_plan_id]) {
        const hasPjObstacle = row.pj_obstacle !== null && row.pj_obstacle !== '';
        const seededFromAgg = !hasPjObstacle && !!row.obstacle_aggregated;
        paraForms.value[row.performance_plan_id] = {
            obstacle: row.pj_obstacle ?? row.obstacle_aggregated ?? '',
            solution: row.pj_solution ?? '',
            follow_up_plan: row.pj_follow_up_plan ?? '',
            saving: false,
            seededFromAgg,
        };
    }

    return paraForms.value[row.performance_plan_id];
}

function saveParaphrase(row: RecapRow) {
    const f = getParaForm(row);
    f.saving = true;
    router.post(route('team-recap.override.store'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'month',
        period_year: props.year,
        period_month: props.month,
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
            <!-- Controls -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <Select
                    :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined"
                    @update:model-value="(v) => navigate({ team: Number(v) })"
                >
                    <SelectTrigger class="w-auto min-w-[16rem]">
                        <SelectValue placeholder="Pilih tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Bulan sebelumnya" @click="prevMonth">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ monthLabel }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Bulan berikutnya" @click="nextMonth">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :class="['inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors', attentionOnly ? 'border-orange-400 bg-orange-50 text-orange-700' : 'border-gray-200 bg-white text-gray-600 hover:border-orange-300 hover:text-orange-600']"
                        @click="attentionOnly = !attentionOnly"
                    >
                        Perlu perhatian
                        <span :class="['rounded-full px-1.5 py-0.5 text-xs', attentionOnly ? 'bg-orange-400 text-white' : 'bg-gray-200 text-gray-600']">
                            {{ segments.reduce((sum, seg) => sum + attentionCount(seg), 0) }}
                        </span>
                    </button>

                    <Button
                        v-if="canManage"
                        size="sm"
                        variant="outline"
                        :disabled="!bulkConfirmIds.length || bulkConfirming"
                        @click="confirmBulk"
                    >
                        Konfirmasi semua (capaian 100%)
                        <span v-if="bulkConfirmIds.length" class="ml-1 rounded-full bg-green-100 px-1.5 py-0.5 text-xs text-green-700">{{ bulkConfirmIds.length }}</span>
                    </Button>
                </div>
            </div>

            <!-- Segments by project -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap tersimpan untuk tim ini pada bulan ini.
            </div>

            <div v-else class="space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                        <span v-if="attentionCount(seg) > 0" class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                            {{ attentionCount(seg) }} perlu perhatian
                        </span>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="text-left">Rencana Kinerja</TableHead>
                                <TableHead class="text-left text-xs">Kontributor</TableHead>
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
                            <template v-for="row in filteredRows(seg)" :key="row.performance_plan_id">
                                <TableRow class="hover:bg-gray-50">
                                    <TableCell class="align-top">
                                        <p class="font-medium text-gray-800">{{ row.rk_description }}</p>
                                        <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                        <p v-if="row.is_overridden" class="mt-0.5 text-xs italic text-blue-500">Telah diparafrase</p>
                                    </TableCell>
                                    <TableCell class="align-top text-xs text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top">
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
                                            :title="expandedRows[row.performance_plan_id] ? 'Tutup panel' : 'Buka panel parafrase'"
                                            @click="toggleExpand(row.performance_plan_id)"
                                        >
                                            <ChevronDown v-if="!expandedRows[row.performance_plan_id]" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <!-- Expand panel -->
                                <TableRow v-if="expandedRows[row.performance_plan_id]" :key="`${row.performance_plan_id}-panel`" class="bg-gray-50">
                                    <TableCell colspan="8" class="px-6 py-4">
                                        <div class="space-y-4">
                                            <!-- Member kendala (read-only) -->
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala (anggota)</p>
                                                <p class="rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.obstacle_aggregated || '—' }}</p>
                                            </div>

                                            <!-- PJ paraphrase inputs -->
                                            <template v-if="canManage">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <Label class="text-xs">Kendala (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).obstacle" :rows="2" class="mt-1 text-sm" />
                                                        <p v-if="getParaForm(row).seededFromAgg" class="mt-0.5 text-xs italic text-gray-400">Prafilled dari kendala anggota</p>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Solusi (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).solution" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">RTL (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).follow_up_plan" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end">
                                                    <Button size="sm" :disabled="getParaForm(row).saving" @click="saveParaphrase(row)">
                                                        Simpan parafrase
                                                    </Button>
                                                </div>
                                            </template>

                                            <!-- Read-only PJ paraphrase (non-PJ) -->
                                            <template v-else>
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
